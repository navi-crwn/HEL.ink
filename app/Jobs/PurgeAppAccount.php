<?php

namespace App\Jobs;

use App\Support\HopApp;
use App\Support\HopApps;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Asks one sibling app (LinkHop, BioHop) to purge the rows and files of a
 * deleted HOP ID: DELETE {app}/internal/accounts/{id}, signed with the app's
 * secret. UserDeletion tries once inline; when the app is down or answers with
 * an error this job repeats the call with backoff for about a day. The purge
 * endpoints work by user id and are idempotent, so repeating is safe.
 */
class PurgeAppAccount implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 8;

    /** @return list<int> seconds */
    public function backoff(): array
    {
        return [60, 300, 1800, 3600, 7200, 14400, 28800];
    }

    /** @param  array<string, list<string>>  $files */
    public function __construct(
        public readonly string $slug,
        public readonly int $userId,
        public readonly array $files,
    ) {}

    public function handle(): void
    {
        $app = HopApps::find($this->slug);
        if (! $app || ! $app->isConfigured()) {
            return;
        }
        if (! self::send($app, $this->userId, $this->files)) {
            throw new \RuntimeException("Account purge not accepted by {$this->slug}");
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('App account purge gave up', ['app' => $this->slug]);
    }

    /**
     * One signed purge call. True when the app answered 2xx; false (with a
     * warning naming the app, never the person) on any other status, timeout
     * or connection error.
     *
     * @param  array<string, list<string>>  $files
     */
    public static function send(HopApp $app, int $userId, array $files): bool
    {
        $secret = (string) $app->secret;
        if (strlen($secret) < 32) {
            return true; // not wired for signed calls: nothing to purge from here
        }
        $path = "/internal/accounts/{$userId}";
        $body = json_encode($files, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $ts = time();
        $sig = hash_hmac('sha256', $ts."\nDELETE\n".$path."\n".hash('sha256', $body), $secret);
        try {
            $res = Http::timeout(5)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'X-Hop-Timestamp' => (string) $ts,
                    'X-Hop-Signature' => $sig,
                ])
                ->withBody($body, 'application/json')
                ->delete(rtrim($app->url, '/').$path);
        } catch (\Throwable $e) {
            Log::warning('App account purge failed', ['app' => $app->slug, 'reason' => $e::class]);

            return false;
        }
        if (! $res->successful()) {
            Log::warning('App account purge not accepted', ['app' => $app->slug, 'status' => $res->status()]);

            return false;
        }

        return true;
    }
}
