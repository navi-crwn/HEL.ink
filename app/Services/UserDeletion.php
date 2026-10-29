<?php

namespace App\Services;

use App\Models\User;
use App\Support\Avatar;
use App\Support\HopApps;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Removes a HOP ID: the hub's own rows (sessions, sign-ins, activity, API
 * tokens, roles), user files (avatars, link QR codes, bio uploads) and the
 * user row. Calls each configured sibling app's `/internal/accounts/{id}` purge
 * endpoint before cascading rows and broadcasts `account.deleted` with the
 * collected file paths so apps with separate storage clean up their copies too.
 */
class UserDeletion
{
    public function delete(User $user): void
    {
        $qrFiles = $this->collectQrFiles($user->id);
        $bioFiles = $this->collectBioFiles($user->id);
        $user->deletedFiles = [
            'qr_codes' => $qrFiles,
            'bio_uploads' => $bioFiles,
        ];

        $this->purgeInApps($user->id, $user->deletedFiles);

        Avatar::delete($user);
        DB::transaction(function () use ($user) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
            DB::table('login_histories')->where('user_id', $user->id)->delete();
            DB::table('activity_logs')->where('user_id', $user->id)->delete();
            DB::table('api_tokens')->where('user_id', $user->id)->delete();
            DB::table('roles')->where('user_id', $user->id)->delete();
            if (Schema::hasTable('links')) {
                DB::table('links')->where('user_id', $user->id)->delete();
            }
            if (Schema::hasTable('bio_pages')) {
                $pageIds = DB::table('bio_pages')->where('user_id', $user->id)->pluck('id')->all();
                if ($pageIds !== [] && Schema::hasTable('bio_links')) {
                    DB::table('bio_links')->whereIn('bio_page_id', $pageIds)->delete();
                }
                DB::table('bio_pages')->where('user_id', $user->id)->delete();
            }
            $user->delete();
        });

        foreach ($qrFiles as $path) {
            Storage::disk('public')->delete($path);
        }
        $this->deleteUnusedBioFiles($bioFiles);
    }

    /** @return list<string> */
    private function collectQrFiles(int $userId): array
    {
        if (! Schema::hasTable('links')) {
            return [];
        }
        $out = [];
        foreach (DB::table('links')->where('user_id', $userId)->whereNotNull('qr_code_path')->pluck('qr_code_path') as $raw) {
            $path = preg_replace('#^/?storage/#', '', trim((string) $raw));
            if ($path !== '' && ! str_contains($path, '..')) {
                $out[] = $path;
            }
        }

        return array_values(array_unique($out));
    }

    /** @return list<string> */
    private function collectBioFiles(int $userId): array
    {
        if (! Schema::hasTable('bio_pages')) {
            return [];
        }
        $rawPaths = [];
        $pages = DB::table('bio_pages')->where('user_id', $userId)->get();
        $pageIds = [];
        foreach ($pages as $page) {
            $pageIds[] = $page->id;
            $rawPaths[] = $page->avatar_url ?? null;
            $rawPaths[] = $page->background_image ?? null;
            if (! empty($page->qr_settings)) {
                $qr = is_string($page->qr_settings) ? (json_decode($page->qr_settings, true) ?: []) : (array) $page->qr_settings;
                $rawPaths[] = $qr['image'] ?? null;
                $rawPaths[] = $qr['logo_url'] ?? null;
            }
        }
        if ($pageIds !== [] && Schema::hasTable('bio_links')) {
            foreach (DB::table('bio_links')->whereIn('bio_page_id', $pageIds)->get() as $link) {
                $rawPaths[] = $link->thumbnail_url ?? null;
                $rawPaths[] = $link->custom_icon ?? null;
                $rawPaths[] = $link->content ?? null;
            }
        }
        $out = [];
        foreach ($rawPaths as $raw) {
            $path = preg_replace('#^/?storage/#', '', trim((string) $raw));
            if (preg_match('#^(avatars|bio-images|bio-thumbnails|bio-icons|qr-logos)/[A-Za-z0-9._-]+$#', (string) $path)) {
                $out[] = $path;
            }
        }

        return array_values(array_unique($out));
    }

    /** @param list<string> $paths */
    private function deleteUnusedBioFiles(array $paths): void
    {
        foreach ($paths as $path) {
            $like = '%'.$path.'%';
            $inUse = false;
            if (Schema::hasTable('bio_pages')) {
                $inUse = DB::table('bio_pages')->where(function ($q) use ($like) {
                    $q->where('avatar_url', 'like', $like)
                        ->orWhere('qr_settings', 'like', $like)
                        ->orWhere('background_value', 'like', $like)
                        ->orWhere('background_image', 'like', $like);
                })->exists();
            }
            if (! $inUse && Schema::hasTable('bio_links')) {
                $inUse = DB::table('bio_links')->where(function ($q) use ($like) {
                    $q->where('thumbnail_url', 'like', $like)
                        ->orWhere('custom_icon', 'like', $like)
                        ->orWhere('content', 'like', $like);
                })->exists();
            }
            if (! $inUse) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    /**
     * Tell configured sibling apps (LinkHop, BioHop) to purge their local files
     * and rows before the user row is deleted.
     *
     * @param  array<string, list<string>>  $files
     */
    private function purgeInApps(int $userId, array $files): void
    {
        $path = "/internal/accounts/{$userId}";
        $body = json_encode($files, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        foreach (HopApps::configured() as $app) {
            if (! in_array($app->slug, ['linkhop', 'biohop'], true)) {
                continue;
            }
            $secret = (string) $app->secret;
            if (strlen($secret) < 32) {
                continue;
            }
            $ts = time();
            $sig = hash_hmac('sha256', $ts."\nDELETE\n".$path."\n".hash('sha256', $body), $secret);
            try {
                Http::timeout(5)
                    ->withHeaders([
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                        'X-Hop-Timestamp' => (string) $ts,
                        'X-Hop-Signature' => $sig,
                    ])
                    ->withBody($body, 'application/json')
                    ->delete(rtrim($app->url, '/').$path);
            } catch (\Throwable $e) {
                Log::warning('App account purge failed', ['app' => $app->slug, 'user_id' => $userId, 'error' => $e->getMessage()]);
            }
        }
    }
}
