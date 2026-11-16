<?php

namespace App\Support;

use App\Http\Controllers\ChangelogController;
use App\Models\FeedbackReport;
use App\Models\HopApp;

/** What the hub knows about the Feedback feature: is it on, how an app's link is cleaned, the names of the apps. */
final class Feedback
{
    /** The name each report can be about, as people read it. */
    public const NAMES = [
        'hub' => 'HOP ID', 'linkhop' => 'LinkHop', 'biohop' => 'BioHop', 'pixelhop' => 'PixelHop',
        'notehop' => 'NoteHop', 'filehop' => 'FileHop', 'payhop' => 'PayHop',
    ];

    /** The form is on when the switch is on and an app that may read reports (Imperium, admin_client) is registered. */
    public static function active(): bool
    {
        if (! config('hop.feedback.enabled')) {
            return false;
        }
        // Asked by the sidebar, the footer and the page itself: once per request is enough.
        $attributes = request()->attributes;
        if (! $attributes->has('hop.feedback.active')) {
            try {
                $on = HopApp::query()->where('status', '!=', HopApp::STATUS_OFF)->get()
                    ->contains(fn (HopApp $a) => $a->can('admin_client'));
            } catch (\Throwable) {
                $on = false; // no database yet (fresh install): the form is not offered
            }
            $attributes->set('hop.feedback.active', $on);
        }

        return (bool) $attributes->get('hop.feedback.active');
    }

    /** The app slug a link named, or `hub` for anything unknown. */
    public static function app(mixed $value): string
    {
        $value = is_string($value) ? $value : '';

        return in_array($value, FeedbackReport::APPS, true) ? $value : 'hub';
    }

    /** The name of the screen the link came from (a route name or route template), or null when it is not one. */
    public static function page(mixed $value): ?string
    {
        return is_string($value) && preg_match('#^[A-Za-z0-9._:/{}-]{1,80}$#', $value) === 1 ? $value : null;
    }

    public static function version(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^[A-Za-z0-9.+-]{1,24}$/', $value) === 1 ? $value : null;
    }

    /** The screen size the page script wrote, like 1440x900. */
    public static function viewport(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{2,5}x\d{2,5}$/', $value) === 1 ? $value : null;
    }

    /** The version of the hub itself, for the hub's own links. */
    public static function hubVersion(): ?string
    {
        try {
            return self::version(ChangelogController::history()['sources']['HEL.ink'] ?? null);
        } catch (\Throwable) {
            return null;
        }
    }

    /** The hub's own link to the form (same tab): its route name tells the form which screen the visitor came from. */
    public static function hubLink(?string $from = null): ?string
    {
        if (! self::active()) {
            return null;
        }
        $from ??= request()->route()?->getName();

        // Always the hub host's address: that is where the form answers with the sign-in session.
        return hub_url('feedback').'?'.http_build_query(array_filter(['app' => 'hub', 'from' => self::page($from), 'v' => self::hubVersion()]));
    }
}
