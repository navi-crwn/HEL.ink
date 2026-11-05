<?php

namespace App\Http\Middleware;

use App\Support\Input;
use Closure;
use Hop\Ui\Locales;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * The page language: ?lang= (remembered in a cookie for a year), then the cookie, then
 * English. The browser's Accept-Language is not used, so English stays the default
 * (owner decision). Texts live in lang/{locale}.json; English is the key itself.
 * The nine languages come from hop/ui (Hop\Ui\Locales); a stored `zh` or `pt` means
 * `zh-CN` or `pt-BR`, and a retired language such as `ar` falls back to English.
 */
class SetLocale
{
    public const COOKIE = 'hop_lang';

    public function handle(Request $request, Closure $next): Response
    {
        $requested = Locales::resolve(Input::text($request->query('lang')));
        $locale = $requested ?? Locales::normalize(Input::text($request->cookie(self::COOKIE, Locales::DEFAULT)));
        app()->setLocale($locale);
        $response = $next($request);
        if ($requested !== null) {
            // Host-only on purpose: cookie()->forever() would add config('session.domain') (the hub host),
            // and a browser drops that cookie when the response comes from the public host (hel.ink).
            $response->headers->setCookie(new Cookie(self::COOKIE, $requested, time() + 5 * 365 * 86400, '/', null, $request->isSecure(), false, false, 'lax'));
        }

        return $response;
    }
}
