<?php

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

        return '<img src="https://flagcdn.com/w20/'.$code.'.png" srcset="https://flagcdn.com/w40/'.$code.'.png 2x" width="20" alt="'.$countryName.'" title="'.$countryName.'" class="inline-block rounded-sm">';
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
