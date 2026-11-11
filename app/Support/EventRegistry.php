<?php

namespace App\Support;

use App\Jobs\SendHopEvent;
use App\Models\HopApp as HopAppRow;
use Illuminate\Support\Facades\Cache;

/**
 * The apps that must be able to receive the hub's account events (config `hop.event_apps`).
 * An event goes only to a row of `apps` that has the `events` capability, so an app that is
 * set up in config but has no such row silently keeps its sessions after a suspend, a delete
 * or a sign-out at the hub.
 */
final class EventRegistry
{
    /**
     * One line per event app that cannot receive events right now. Empty when all is well.
     *
     * @return list<string>
     */
    public static function warnings(): array
    {
        $out = [];
        foreach (self::expected() as $slug) {
            try {
                $row = HopAppRow::registered()->where('slug', $slug)->first();
            } catch (\Throwable) {
                return ['The apps table cannot be read, so it is unknown which apps receive account events.'];
            }
            if (! $row) {
                $out[] = "{$slug}: no row in the apps table, so it gets no account events (suspend, delete, sign-out). Run the HopAppsSeeder.";
            } elseif (! $row->can('events')) {
                $out[] = "{$slug}: its apps row has no \"events\" capability, so it gets no account events. Add it to the row's capabilities.";
            } elseif (! $row->isConfigured()) {
                $out[] = "{$slug}: its apps row has no usable URL or secret (32+ characters), so account events cannot be sent.";
            }
        }
        foreach (self::expected() as $slug) {
            $f = (array) Cache::get(SendHopEvent::failureKey($slug), []);
            if (($f['count'] ?? 0) > 0) {
                $out[] = "{$slug}: {$f['count']} account event(s) could not be delivered after every retry (last: {$f['last_type']}, {$f['last_at']}). Check the app's secret and that it is up; until then a sign-out, suspend or delete may not have reached it, and its sessions end at the next silent re-check.";
            }
        }

        return $out;
    }

    /**
     * Adds the rows that are missing, never touches one that exists (its secret, URL and
     * capabilities stay as the owner set them, a switched-off row stays switched off).
     *
     * @return array<string, string> slug => created | exists | skipped: <why>
     */
    public static function ensure(): array
    {
        $result = [];
        foreach (self::expected() as $slug) {
            if (HopAppRow::query()->where('slug', $slug)->exists()) {
                $result[$slug] = 'exists';

                continue;
            }
            $app = (array) config("hop.apps.{$slug}", []);
            $spec = (array) config("hop.event_apps.{$slug}", []);
            $registry = ! empty($app['registry']);
            // As the hub itself resolves it: the app's own secret, then (config apps only) the shared one.
            $secret = (string) ($spec['secret'] ?? $app['secret'] ?? ($registry ? '' : config('hop.sso_secret')));
            $url = rtrim((string) ($app['url'] ?? ''), '/');
            if (strlen($secret) < 32) {
                $result[$slug] = 'skipped: no secret of 32+ characters in the environment';

                continue;
            }
            if (preg_match('#^https?://#', $url) !== 1) {
                $result[$slug] = 'skipped: no app URL configured';

                continue;
            }
            HopAppRow::create([
                'slug' => $slug,
                'name' => (string) ($app['name'] ?? ucfirst($slug)),
                'url' => $url,
                'callback_path' => (string) ($spec['callback_path'] ?? $app['callback'] ?? '/auth/sso/callback'),
                'start_path' => (string) ($spec['start_path'] ?? $app['start'] ?? '/auth/sso'),
                'pages' => $spec['pages'] ?? ['default' => '/'],
                'continue_page' => (string) ($spec['continue_page'] ?? 'default'),
                'menu_label' => $spec['menu_label'] ?? null,
                'icon' => $app['icon'] ?? null,
                'color' => $app['color'] ?? null,
                'status' => HopAppRow::STATUS_ACTIVE,
                'secret' => $secret,
                'capabilities' => self::capabilitiesFor($slug),
                'sort_order' => (int) ($spec['sort_order'] ?? 100),
            ]);
            $result[$slug] = 'created';
        }

        return $result;
    }

    /**
     * The capabilities a new row of this app starts with: `events`, plus what config/hop.php lists
     * for it (FileHop: admin_api, so Imperium polls its /hop/health). Only for a row that does not
     * exist yet; an existing row's capabilities are the owner's and are never rewritten from here.
     *
     * @return list<string>
     */
    public static function capabilitiesFor(string $slug): array
    {
        return array_values(array_unique(array_merge(['events'], (array) config("hop.event_apps.{$slug}.capabilities", []))));
    }

    /** @return list<string> slugs of the event apps that are enabled in config/hop.php */
    private static function expected(): array
    {
        return array_values(array_filter(
            array_keys((array) config('hop.event_apps', [])),
            fn (string $slug) => ! empty(config("hop.apps.{$slug}.enabled")) && ! empty(config("hop.apps.{$slug}.url")),
        ));
    }
}
