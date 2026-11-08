<?php

namespace App\Support;

use App\Models\HopApp as HopAppRow;

/**
 * Imperium, the optional admin control panel (github.com/navi-crwn/Imperium), as a row of `apps`:
 * hidden from menus, signing in through HOP ID like any app, and allowed to call the signed admin
 * API (`admin_client`). Production added this row by hand; a self-hosted hub gets it from
 * HopAppsSeeder once HOP_CONTROL_URL and HOP_SSO_SECRET_IMPERIUM are set.
 */
final class ControlRegistry
{
    public const SLUG = 'imperium';

    /**
     * Adds the row when it is missing, never touches one that exists.
     *
     * @return string created | exists | skipped: <why>
     */
    public static function ensure(): string
    {
        $url = rtrim((string) config('hop.control_url'), '/');
        if ($url === '') {
            return 'skipped: HOP_CONTROL_URL is not set (Imperium is optional)';
        }
        if (HopAppRow::query()->where('slug', self::SLUG)->exists()) {
            return 'exists';
        }
        if (preg_match('#^https?://#', $url) !== 1) {
            return 'skipped: HOP_CONTROL_URL is not an http(s) address';
        }
        $secret = (string) config('hop.control_secret');
        if (strlen($secret) < 32) {
            return 'skipped: HOP_SSO_SECRET_IMPERIUM needs 32+ characters';
        }
        HopAppRow::create([
            'slug' => self::SLUG,
            'name' => 'Imperium',
            'url' => $url,
            'callback_path' => '/auth/sso/callback',
            'start_path' => '/auth/sso',
            'pages' => ['default' => '/'],
            'continue_page' => 'default',
            'menu_label' => null,
            'status' => HopAppRow::STATUS_ACTIVE,
            'secret' => $secret,
            'capabilities' => ['admin_client'],
            'sort_order' => 1,
            'hidden' => true,
        ]);

        return 'created';
    }
}
