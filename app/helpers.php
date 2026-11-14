<?php

use Illuminate\Support\Facades\Route;

if (! function_exists('public_url')) {
    /** A URL on the public host (hel.ink): public_url('l/abc') → https://hel.ink/l/abc. */
    function public_url(string $path = ''): string
    {
        $base = (string) config('hop.public_url');
        $path = ltrim($path, '/');

        return $path === '' ? $base : $base.'/'.$path;
    }
}
if (! function_exists('hub_url')) {
    /** A URL on the hub host (hub.hel.ink). */
    function hub_url(string $path = ''): string
    {
        $base = (string) config('hop.hub_url');
        $path = ltrim($path, '/');

        return $path === '' ? $base : $base.'/'.$path;
    }
}
if (! function_exists('country_flag')) {
    function country_flag(?string $countryCode): string
    {
        if (! $countryCode || strlen($countryCode) !== 2) {
            return '<span class="inline-block w-5 h-4" title="Unknown">🌍</span>';
        }
        $code = strtolower($countryCode);
        $countryName = country_name(strtoupper($countryCode));

        // The country code, never a picture from another host (the visitor's address would go there).
        return '<span class="inline-block rounded-sm bg-slate-200 px-1 text-[10px] font-semibold leading-4 text-slate-600 dark:bg-slate-700 dark:text-slate-200" title="'.e($countryName).'">'.e(strtoupper($code)).'</span>';
    }
}
if (! function_exists('country_name')) {
    function country_name(?string $countryCode): string
    {
        if (! $countryCode) {
            return 'Unknown';
        }
        $countries = [
            'US' => 'United States', 'ID' => 'Indonesia', 'GB' => 'United Kingdom', 'CA' => 'Canada', 'AU' => 'Australia',
            'DE' => 'Germany', 'FR' => 'France', 'JP' => 'Japan', 'CN' => 'China', 'IN' => 'India', 'BR' => 'Brazil',
            'RU' => 'Russia', 'SG' => 'Singapore', 'MY' => 'Malaysia', 'TH' => 'Thailand', 'PH' => 'Philippines',
            'VN' => 'Vietnam', 'NL' => 'Netherlands', 'IT' => 'Italy', 'ES' => 'Spain',
        ];

        return $countries[strtoupper($countryCode)] ?? $countryCode;
    }
}
if (! function_exists('public_host')) {
    /** The public host without www., for display (hel.ink). */
    function public_host(): string
    {
        return preg_replace('/^www\./', '', (string) parse_url(public_url(), PHP_URL_HOST));
    }
}

if (! function_exists('app_page_url')) {
    /** hel.ink/apps/{app}, or the app's tab on the home page when the hub and hel.ink share one host. */
    function app_page_url(string $slug): string
    {
        return Route::has('apps.show') ? route('apps.show', $slug) : route('home').'#'.$slug;
    }
}
if (! function_exists('hop_home_url')) {
    /** Where "back" and the logo of a public page lead: the dashboard for a signed-in member, the public home for a visitor. */
    function hop_home_url(): string
    {
        return auth()->check() ? hub_url('dashboard') : route('home');
    }
}
if (! function_exists('hop_error_home')) {
    /**
     * The way back on an error page: the dashboard (and its label) for a member, the public home for a visitor.
     * An error page must never fail, so a broken session or database only means the visitor's link.
     *
     * @return array{0: string, 1: string} URL and label
     */
    function hop_error_home(): array
    {
        try {
            if (auth()->check()) {
                return [hub_url('dashboard'), __('Back to your dashboard')];
            }
        } catch (Throwable) {
        }

        return [url('/'), __('Back to the home page')];
    }
}
if (! function_exists('hop_app_url')) {
    /** Where "Open <app>" leads: the app's dashboard through the hub launcher for a member, its public page for a visitor. */
    function hop_app_url(string $slug, string $publicUrl): string
    {
        if (! auth()->check()) {
            return $publicUrl;
        }

        return $slug === 'hub' ? hub_url('dashboard') : hub_url('apps/'.$slug);
    }
}
