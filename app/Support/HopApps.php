<?php

namespace App\Support;

/** The HOP apps from config/hop.php, enabled ones only, in launcher order. */
final class HopApps
{
    /** @return list<HopApp> */
    public static function all(): array
    {
        $out = [];
        foreach ((array) config('hop.apps', []) as $slug => $c) {
            if (! is_array($c) || empty($c['enabled']) || empty($c['url'])) {
                continue;
            }
            $out[] = HopApp::fromConfig((string) $slug, $c);
        }

        return $out;
    }

    public static function find(string $slug): ?HopApp
    {
        foreach (self::all() as $app) {
            if ($app->slug === $slug) {
                return $app;
            }
        }

        return null;
    }

    /** Apps that can sign people in (URL and secret set). @return list<HopApp> */
    public static function configured(): array
    {
        return array_values(array_filter(self::all(), fn (HopApp $a) => $a->isConfigured()));
    }

    /** @return list<string> */
    public static function origins(): array
    {
        $out = [];
        foreach (self::configured() as $app) {
            if ($o = $app->origin()) {
                $out[] = $o;
            }
        }

        return array_values(array_unique($out));
    }
}
