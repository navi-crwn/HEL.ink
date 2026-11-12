<?php

namespace App\Support;

use Hop\Ui\Locales;
use Illuminate\Http\Request;

/**
 * What search engines read on the public host (hel.ink): one canonical URL per page and
 * language, the language alternates, and the structured data of the home page.
 * English is the default language and has no ?lang=; every other language is ?lang={code}.
 */
final class Seo
{
    public const NAME = 'HEL.ink';

    /** The public URL of a path in one language, without any other query string. */
    public static function url(string $path = '', ?string $locale = null): string
    {
        $url = rtrim((string) config('hop.public_url'), '/').'/'.trim($path, '/');
        $locale = Locales::resolve($locale);

        return $locale === null || $locale === Locales::DEFAULT ? $url : $url.'?lang='.$locale;
    }

    /** The canonical URL of the page being served: its path, plus ?lang= when the request asked for a language. */
    public static function canonical(Request $request): string
    {
        return self::url($request->path(), Input::text($request->query('lang')));
    }

    /** @return array<string, string> hreflang => URL: the nine languages, then x-default */
    public static function alternates(string $path = ''): array
    {
        $out = [];
        foreach (Locales::codes() as $code) {
            $out[$code] = self::url($path, $code);
        }
        $out['x-default'] = self::url($path);

        return $out;
    }

    /**
     * JSON-LD for the home page: who publishes the site, the site itself and the apps of the family.
     *
     * @param  list<HopApp>  $apps  the same list the home page shows (HopApps::all())
     * @return array<string, mixed>
     */
    public static function homeGraph(array $apps): array
    {
        $home = self::url();
        $items = [];
        foreach (array_values($apps) as $i => $app) {
            $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $app->name, 'url' => $app->url];
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Organization',
                    '@id' => $home.'#organization',
                    'name' => self::NAME,
                    'url' => $home,
                    'logo' => route('brand.logo'),
                    'sameAs' => array_values(array_filter([(string) config('hop.github.org_url')])),
                ],
                [
                    '@type' => 'WebSite',
                    '@id' => $home.'#website',
                    'name' => self::NAME,
                    'url' => $home,
                    'inLanguage' => Locales::codes(),
                    'publisher' => ['@id' => $home.'#organization'],
                ],
                [
                    '@type' => 'ItemList',
                    '@id' => $home.'#apps',
                    'name' => 'HOP apps',
                    'numberOfItems' => count($items),
                    'itemListElement' => $items,
                ],
            ],
        ];
    }
}
