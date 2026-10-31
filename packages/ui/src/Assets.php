<?php

namespace Hop\Ui;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Vite;

/**
 * The HOP family asset manifest (https://hel.ink/hop-assets/apps.json): fonts,
 * floating tiles and every app's marks, each with an SRI hash. Read from the hub so
 * a new asset version reaches every app without a release; cached for five minutes.
 * When the hub cannot be reached, the copy bundled with this package is used, so a
 * page never breaks because hel.ink is slow.
 */
final class Assets
{
    public const URL = 'https://hel.ink/hop-assets/apps.json';

    /** @return array{version: string, base: string, assets: array<string, array{url: string, integrity: string}>, apps: list<array<string, mixed>>} */
    public static function manifest(): array
    {
        if (! (bool) config('hop-ui.assets.remote', ! app()->runningUnitTests())) {
            return self::bundled();
        }

        return Cache::remember('hop-ui:assets', 300, function () {
            try {
                $res = Http::timeout(2)->connectTimeout(1)->acceptJson()->get((string) config('hop-ui.assets.url', self::URL));
                $data = $res->ok() ? $res->json() : null;
                if (is_array($data) && isset($data['assets'], $data['apps'])) {
                    return $data;
                }
            } catch (\Throwable $e) {
                Log::warning('hop-ui: asset manifest unavailable, using the bundled copy', ['error' => $e->getMessage()]);
            }

            return self::bundled();
        });
    }

    /** @return array<string, mixed> */
    public static function bundled(): array
    {
        static $copy = null;

        return $copy ??= json_decode((string) file_get_contents(__DIR__.'/../resources/hop-assets/apps.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return array{url: string, integrity: string}|null */
    public static function asset(string $key): ?array
    {
        return self::manifest()['assets'][$key] ?? null;
    }

    /** Public apps in family order, the current one marked. @return list<array<string, mixed>> */
    public static function family(?string $current = null): array
    {
        $current ??= (string) config('hop.current', '');
        $out = [];
        foreach (self::manifest()['apps'] as $app) {
            if (empty($app['public'])) {
                continue;
            }
            $app['current'] = $app['slug'] === $current;
            $out[] = $app;
        }

        return $out;
    }

    /** The CSP nonce Vite uses, when the app sets one. */
    public static function nonce(): ?string
    {
        return class_exists(Vite::class) ? Vite::cspNonce() : null;
    }
}
