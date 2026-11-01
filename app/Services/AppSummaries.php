<?php

namespace App\Services;

use App\Models\User;
use App\Support\HopApp;
use App\Support\HopSignature;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * The numbers on the dashboard's app cards: what each app says it holds for this
 * one account (links, pages, files, notes), asked server to server with a signed
 * request. Counts only. A slow or failing app costs a short timeout once, then
 * its card has no numbers for a minute; the page never waits on an app twice.
 *
 * config/hop.php `summaries` says how to ask each app and which fields to show.
 */
final class AppSummaries
{
    public const TTL = 300;

    public const FAIL_TTL = 60;

    public const TIMEOUT = 2;

    /**
     * @param  iterable<HopApp>  $apps
     * @param  array<string, array{ok: ?bool}>  $status  watchdog state by slug; apps known to be down are skipped
     * @return array<string, list<array{label: string, value: string}>> rows by app slug (only apps with numbers)
     */
    public function for(User $user, iterable $apps, array $status = []): array
    {
        $out = [];
        $ask = [];
        foreach ($apps as $app) {
            $rule = config('hop.summaries.'.$app->slug);
            if (! is_array($rule) || ! $app->isConfigured() || ($status[$app->slug]['ok'] ?? null) === false) {
                continue;
            }
            $key = $this->key($user, $app);
            $cached = Cache::get($key);
            if (is_array($cached)) {
                if ($cached !== []) {
                    $out[$app->slug] = $cached;
                }

                continue;
            }
            $ask[$app->slug] = [$app, $rule, $key];
        }
        if ($ask === []) {
            return $out;
        }

        $responses = Http::pool(function (Pool $pool) use ($ask, $user) {
            foreach ($ask as $slug => [$app, $rule]) {
                $path = str_replace('{id}', (string) $user->id, (string) $rule['path']);
                $pool->as($slug)->timeout(self::TIMEOUT)->connectTimeout(self::TIMEOUT)->acceptJson()
                    ->withHeaders($this->headers($app, $rule, $path))->get(rtrim($app->url, '/').$path);
            }
        });

        foreach ($ask as $slug => [$app, $rule, $key]) {
            $rows = [];
            try {
                $res = $responses[$slug] ?? null;
                if ($res instanceof Response && $res->successful() && $res->json('ok') === true) {
                    $rows = $this->rows((array) $res->json('data'), (array) $rule['fields']);
                }
            } catch (\Throwable) {
                // A failed call is the same as no numbers.
            }
            Cache::put($key, $rows, $rows === [] ? self::FAIL_TTL : self::TTL);
            if ($rows !== []) {
                $out[$slug] = $rows;
            }
        }

        return $out;
    }

    /** Forget one account's numbers (after the account is changed or deleted). */
    public function forget(User $user, iterable $apps): void
    {
        foreach ($apps as $app) {
            Cache::forget($this->key($user, $app));
        }
    }

    private function key(User $user, HopApp $app): string
    {
        return 'hub:summary:'.$app->slug.':'.$user->id;
    }

    /** @return array<string, string> */
    private function headers(HopApp $app, array $rule, string $path): array
    {
        $secret = (string) $app->secret;
        if (($rule['auth'] ?? 'hub') === 'hop') {
            return HopSignature::headersWithSecret($secret, 'GET', $path, '');
        }
        $ts = time();

        return [
            'X-Hop-Timestamp' => (string) $ts,
            'X-Hop-Signature' => hash_hmac('sha256', $ts."\nGET\n".$path."\n".hash('sha256', ''), $secret),
        ];
    }

    /**
     * @param  array<string, string>  $fields  key => "Label" or "Label:format"
     * @return list<array{label: string, value: string}>
     */
    private function rows(array $data, array $fields): array
    {
        $rows = [];
        foreach ($fields as $key => $spec) {
            if (! isset($data[$key]) || ! is_numeric($data[$key])) {
                continue;
            }
            [$label, $format] = array_pad(explode(':', (string) $spec, 2), 2, null);
            $n = (int) $data[$key];
            $rows[] = ['label' => $label, 'value' => $format === 'bytes' ? self::bytes($n) : number_format($n)];
        }

        return $rows;
    }

    private static function bytes(int $n): string
    {
        foreach (['B', 'KB', 'MB', 'GB', 'TB'] as $i => $unit) {
            if ($n < 1024 || $unit === 'TB') {
                return ($i === 0 ? (string) $n : rtrim(rtrim(number_format($n, 1), '0'), '.')).' '.$unit;
            }
            $n /= 1024;
        }

        return '';
    }
}
