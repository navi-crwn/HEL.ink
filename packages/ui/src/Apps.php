<?php

namespace Hop\Ui;

/**
 * The apps in config/hop.php as plain arrays for the navigation, plus the
 * hub itself. Works the same in the hub and in the consumer apps.
 *
 * @phpstan-type App array{slug: string, name: string, url: string, icon: string, color: string, current: bool, open: string}
 */
final class Apps
{
    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        $current = (string) config('hop.current', '');
        $hub = rtrim((string) config('hop.hub_url', config('hop-auth.hub_url', 'https://hub.hel.ink')), '/');
        $out = [[
            'slug' => 'hub', 'name' => 'HOP', 'url' => $hub, 'open' => $hub.'/dashboard',
            'icon' => 'hop', 'color' => 'slate', 'current' => $current === 'hub',
        ]];
        foreach ((array) config('hop.apps', []) as $slug => $c) {
            if (! is_array($c) || (isset($c['enabled']) && ! $c['enabled']) || empty($c['url'])) {
                continue;
            }
            $url = rtrim((string) $c['url'], '/');
            $out[] = [
                'slug' => (string) $slug,
                'name' => (string) ($c['name'] ?? ucfirst((string) $slug)),
                'url' => $url,
                // Opening from the hub goes through SSO; from an app, straight to the app's SSO start.
                'open' => $current === 'hub' ? $hub.'/apps/'.$slug : $url.($c['start'] ?? '/auth/sso'),
                'icon' => (string) ($c['icon'] ?? 'globe'),
                'color' => (string) ($c['color'] ?? 'indigo'),
                'current' => $current === (string) $slug,
            ];
        }

        return $out;
    }

    public static function icon(string $name): string
    {
        return self::ICONS[$name] ?? self::ICONS['globe'];
    }

    public const ICONS = [
        'hop' => 'M4 20V9l8-6 8 6v11h-5v-6H9v6H4Z',
        'link' => 'M10 14a4 4 0 0 0 5.66 0l3-3a4 4 0 0 0-5.66-5.66l-1 1M14 10a4 4 0 0 0-5.66 0l-3 3a4 4 0 0 0 5.66 5.66l1-1',
        'bio' => 'M8 3h8a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm1 4h6M9 11h6m-6 4h3',
        'image' => 'M4 5h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Zm4.5 6a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3ZM21 15l-5-5-9 9',
        'note' => 'M6 3h9l4 4v14H6V3Zm8 0v5h5M9 12h6m-6 4h4',
        'globe' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0 0c2.5-2.5 3.5-5.5 3.5-9S14.5 5.5 12 3m0 18c-2.5-2.5-3.5-5.5-3.5-9S9.5 5.5 12 3M3.5 9h17m-17 6h17',
    ];
}
