<?php

namespace App\Support;

use Hop\Ui\Assets;

/**
 * The HOP apps as the home page tabs and the /apps/{app} pages tell them: name, mark and
 * address from the asset manifest; story, features and call to action as translatable
 * text (lang/*.json, English is the key).
 */
final class FamilyStory
{
    public const APPS = ['linkhop', 'biohop', 'pixelhop', 'notehop'];

    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        $manifest = collect(Assets::family())->keyBy('slug');
        $text = [
            'linkhop' => [
                'kicker' => __('Short links'),
                'story' => __('Long links are hard to share and you never know who clicks them. LinkHop makes them short, counts every click without tracking people, and checks links for scams.'),
                'features' => [__('Your own short links at hel.ink/l/…'), __('QR codes in PNG, SVG or JPG'), __('Clicks by country, device and referrer'), __('An API with scoped tokens, and ShareX')],
                'cta' => __('Shorten a link'),
            ],
            'biohop' => [
                'kicker' => __('Link in bio'),
                'story' => __('One page for everything you share: links, profiles, music, videos and a contact card, at hel.ink/b/yourname.'),
                'features' => [__('20+ themes, or your own colors and CSS'), __('Blocks for links, embeds, maps and vCards'), __('Views and clicks per block'), __('Password or 18+ gate')],
                'cta' => __('Create your bio'),
            ],
            'pixelhop' => [
                'kicker' => __('Image hosting'),
                'story' => __('Drop an image, video or PDF and get a link that previews well everywhere. Your location is removed before anyone sees the file.'),
                'features' => [__('Images, videos and PDFs'), __('Albums and direct links'), __('A photo studio that runs in your browser'), __('An API and ShareX config')],
                'cta' => __('Upload a file'),
            ],
            'notehop' => [
                'kicker' => __('Encrypted notes'),
                'story' => __('Notes only the people you choose can read. The note is locked in your browser before it is saved, so not even the server can read it.'),
                'features' => [__('End-to-end encrypted in the browser'), __('Read-only, edit or reply links'), __('Burn after reading and timed notes'), __('Nine languages')],
                'cta' => __('Write a note'),
            ],
        ];
        $out = [];
        foreach (self::APPS as $slug) {
            $app = $manifest->get($slug);
            if (! $app) {
                continue;
            }
            $out[$slug] = $text[$slug] + [
                'slug' => $slug,
                'name' => $app['name'],
                'url' => $app['url'],
                'host' => parse_url($app['url'], PHP_URL_HOST),
                'mark' => $app['icons']['mark']['url'],
                'color' => $app['color'] ?? '#6366f1',
                'repo' => $app['repo'] ?? null,
            ];
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    public static function find(string $slug): ?array
    {
        return self::all()[$slug] ?? null;
    }
}
