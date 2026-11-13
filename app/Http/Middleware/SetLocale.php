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
 *
 * Family cookie (train 4): on hel.ink and its subdomains the cookie carries Domain=.hel.ink, so a
 * choice made on the public home (hel.ink) holds on the dashboard (hub.hel.ink), in the other HOP apps
 * and the other way round. Elsewhere (self-hosted copies, localhost) it stays host-only.
 */
class SetLocale
{
    public const COOKIE = 'hop_lang';

    /** One-time clean-up marker: once `hop_pref=2` is present, the old host-only language cookie has been replaced. */
    public const MARKER = 'hop_pref';

    private const YEAR = 31536000;

    /** `.hel.ink` for a request to hel.ink or any of its subdomains; null (host-only cookie) for every other host. */
    public static function sharedDomain(Request $request): ?string
    {
        $host = strtolower($request->getHost());

        return $host === 'hel.ink' || str_ends_with($host, '.hel.ink') ? '.hel.ink' : null;
    }

    /**
     * The value of cookie $name in the raw Cookie header; when it appears more than once the last one counts (browsers send
     * the oldest first, so the last is the newer family cookie, while PHP alone keeps the first). Without such a pair in
     * the header, the value PHP parsed; '' when there is none. Parts that are not name=value are ignored.
     */
    public static function lastCookie(Request $request, string $name): string
    {
        $value = null;
        foreach (explode(';', (string) $request->headers->get('Cookie', '')) as $part) {
            [$n, $v] = array_pad(explode('=', trim($part), 2), 2, null);
            if ($v !== null && trim($n) === $name) {
                $value = trim($v);
            }
        }

        return $value ?? Input::text($request->cookie($name));
    }

    public function handle(Request $request, Closure $next): Response
    {
        $requested = Locales::resolve(Input::text($request->query('lang')));
        $sent = self::lastCookie($request, self::COOKIE);
        $carried = Locales::resolve($sent);
        $locale = $requested ?? Locales::normalize($sent);
        app()->setLocale($locale);
        $response = $next($request);

        $domain = self::sharedDomain($request);
        $secure = $domain !== null || $request->isSecure();
        if ($requested !== null) {
            $response->headers->setCookie($this->cookie(self::COOKIE, $requested, $domain, $secure));
        }
        // A browser that holds the old host-only cookie and the new domain cookie sends both, the old one first, so the
        // old choice would win. Once per browser and host: drop the old one, keep its value under the domain, mark it done.
        if ($domain !== null && $request->cookie(self::MARKER) !== '2') {
            $response->headers->setCookie(new Cookie(self::COOKIE, '', 1, '/', null, $secure, false, false, 'lax'));
            if ($requested === null && $carried !== null) {
                $response->headers->setCookie($this->cookie(self::COOKIE, $carried, $domain, $secure));
            }
            $response->headers->setCookie($this->cookie(self::MARKER, '2', null, $secure));
        }

        return $response;
    }

    private function cookie(string $name, string $value, ?string $domain, bool $secure): Cookie
    {
        return new Cookie($name, $value, time() + self::YEAR, '/', $domain, $secure, false, false, 'lax');
    }
}
