<?php

namespace App\Support;

/**
 * The HOP apps: config/hop.php first (enabled ones), then every app in the HOP
 * ID registry table `apps` that config does not already describe and that is
 * not switched off. all() is what people see (launcher, home page, footer);
 * everything() adds hidden apps such as Imperium, for SSO and the CSP.
 */
final class HopApps
{
    /** Visible apps, in launcher order. @return list<HopApp> */
    public static function all(): array
    {
        return array_values(array_filter(self::everything(), fn (HopApp $a) => ! $a->hidden));
    }

    /** Every app, hidden ones included. @return list<HopApp> */
    public static function everything(): array
    {
        $out = [];
        $rows = [];
        foreach (self::registry() as $row) {
            $rows[(string) $row->slug] = $row;
        }
        foreach ((array) config('hop.apps', []) as $slug => $c) {
            $slug = (string) $slug;
            if (! is_array($c) || empty($c['enabled']) || empty($c['url'])) {
                continue;
            }
            // 'registry' => true: shown from config, but signs in with the registry row's own secret and callback.
            $out[$slug] = ! empty($c['registry']) && isset($rows[$slug])
                ? HopApp::fromRegistry($rows[$slug], $c)
                : HopApp::fromConfig($slug, $c);
        }
        foreach ($rows as $slug => $row) {
            if (! isset($out[$slug])) {
                $out[$slug] = HopApp::fromRegistry($row);
            }
        }

        return array_values($out);
    }

    /** Registry rows that are not switched off; none when the table is missing (fresh dev database). */
    private static function registry(): iterable
    {
        try {
            return \App\Models\HopApp::query()->registered()->get();
        } catch (\Throwable) {
            return [];
        }
    }

    public static function find(string $slug): ?HopApp
    {
        foreach (self::everything() as $app) {
            if ($app->slug === $slug) {
                return $app;
            }
        }

        return null;
    }

    /** Apps that can sign people in (URL and secret set). @return list<HopApp> */
    public static function configured(): array
    {
        return array_values(array_filter(self::everything(), fn (HopApp $a) => $a->isConfigured()));
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
