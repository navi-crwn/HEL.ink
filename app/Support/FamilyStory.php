<?php

namespace App\Support;

use Hop\Ui\Assets;

/**
 * The HOP apps as the home page tabs and the /apps/{app} pages tell them: name, mark and
 * address from the asset manifest; story, features, bento grid and call to action as translatable
 * text (lang/*.json, English is the key).
 */
final class FamilyStory
{
    public const APPS = ['linkhop', 'biohop', 'pixelhop', 'notehop', 'filehop', 'payhop'];

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
                'steps' => [
                    [__('Paste your link'), __('Paste any long URL and choose an optional custom alias slug.')],
                    [__('Customize & Protect'), __('Generate high-res QR codes, set an optional password, or add UTM tags.')],
                    [__('Track Safely'), __('Share your link at hel.ink/l/… and view privacy-safe click metrics.')],
                ],
                'faqs' => [
                    [__('Where do links live?'), __('Short links are hosted at hel.ink/l/{slug}. Legacy links redirect automatically.')],
                    [__('How does LinkHop protect users?'), __('Destination targets are checked against automated security blocklists to stop phishing.')],
                    [__('Are visitor IPs tracked?'), __('No. IP addresses are cryptographically hashed with rotating salts and purged after 30 days.')],
                ],
            ],
            'biohop' => [
                'kicker' => __('Link in bio'),
                'story' => __('One page for everything you share: links, profiles, music, videos and a contact card, at hel.ink/b/yourname.'),
                'features' => [__('20+ themes, or your own colors and CSS'), __('Blocks for links, embeds, maps and vCards'), __('Views and clicks per block'), __('Password or 18+ gate')],
                'cta' => __('Create your bio'),
                'steps' => [
                    [__('Claim your name'), __('Choose your unique handle at hel.ink/b/yourname with your HOP ID.')],
                    [__('Arrange your blocks'), __('Add links, videos, music embeds, maps, and portfolio cards with drag-and-drop.')],
                    [__('Pick your style'), __('Select from 20+ responsive themes or fine-tune with custom CSS.')],
                ],
                'faqs' => [
                    [__('Is BioHop free?'), __('Yes, completely free with no premium tiers or paywalled themes.')],
                    [__('Can I password-protect my page?'), __('Yes, you can enable a passcode gate or an 18+ age verification check.')],
                    [__('Are bio slugs separate from links?'), __('Yes, bio pages live under /b/ and do not conflict with short links under /l/.')],
                ],
            ],
            'pixelhop' => [
                'kicker' => __('Image hosting'),
                'story' => __('Drop an image, video or PDF and get a link that previews well everywhere. Your location is removed before anyone sees the file.'),
                'features' => [__('Images, videos and PDFs'), __('Albums and direct links'), __('A photo studio that runs in your browser'), __('An API and ShareX config')],
                'cta' => __('Upload a file'),
                'steps' => [
                    [__('Drag & Drop'), __('Upload images, PDFs, or short clips quickly from any desktop or mobile browser.')],
                    [__('Edit & Sanitize'), __('EXIF location metadata is automatically stripped; edit or crop in the browser studio.')],
                    [__('Share anywhere'), __('Copy direct image links or album URLs hosted cleanly at p.hel.ink.')],
                ],
                'faqs' => [
                    [__('What files are supported?'), __('Photos (PNG, JPG, WebP, GIF, SVG), video clips, and document files (PDF).')],
                    [__('What is the difference between pixel and p?'), __('pixel.hel.ink is your management studio; p.hel.ink serves shared media files.')],
                    [__('Can I use ShareX?'), __('Yes, download your custom .sxcu configuration file directly from your user dashboard.')],
                ],
            ],
            'notehop' => [
                'kicker' => __('Encrypted notes'),
                'story' => __('Notes only the people you choose can read. By default a note is locked in your browser before it is saved, so not even the server can read it.'),
                'features' => [__('End-to-end encrypted in the browser'), __('Read-only, edit or reply links'), __('Burn after reading and timed notes'), __('Nine languages')],
                'cta' => __('Write a note'),
                'steps' => [
                    [__('Write your note'), __('Type your private note or attach a document in your browser.')],
                    [__('Client-side encryption'), __('AES-GCM encrypts your data locally; the decryption key stays in the URL fragment (#k=...).')],
                    [__('Share securely'), __('Send the link (n.hel.ink/slug#k=...); set it to burn after reading or expire on a timer.')],
                ],
                'faqs' => [
                    [__('Can the server read my note?'), __('Not an encrypted note, which is the default: its cryptographic key is in the URL hash fragment, which browsers never send to the server. A basic note is readable by the server.')],
                    [__('What happens if I lose the link?'), __('Because of zero-knowledge architecture, lost keys cannot be recovered by anyone.')],
                    [__('What is burn-after-reading?'), __('The note is permanently deleted from server storage the instant it is viewed.')],
                ],
            ],
            'filehop' => [
                'kicker' => __('File conversion'),
                'story' => __('Turn an image, a PDF, a table or an archive into the format you need, right in your browser. Your files never leave your device: there is nothing to upload.'),
                'features' => [__('Images to WebP, JPEG, PNG or PDF'), __('Merge, split, rotate and pick PDF pages'), __('CSV, JSON, YAML and TSV in any direction'), __('ZIP listings and SHA-256 checksums')],
                'cta' => __('Convert a file'),
                'steps' => [
                    [__('Drop your files'), __('Drag files onto the page, paste them, or pick them; up to 50 at a time.')],
                    [__('Pick a format'), __('Each file gets the formats that make sense for it, or set one for a whole group.')],
                    [__('Convert and download'), __('Your own device does the work; download each result, or all of them in one ZIP.')],
                ],
                'faqs' => [
                    [__('Are my files uploaded?'), __('No. FileHop converts in your browser tab. Watch your browser\'s network tab while it runs: nothing is sent.')],
                    [__('Do I need an account?'), __('No. Every conversion works without one. A HOP ID only adds your account menu for now.')],
                    [__('Which formats does FileHop handle?'), __('Images, PDF, CSV/TSV/JSON/YAML, Markdown/HTML/text and ZIP/TAR/GZIP archives. Video, audio and DOCX are not there yet.')],
                ],
            ],
            'payhop' => [
                'kicker' => __('Payment QR'),
                'story' => __('Make a QR code that pays the right person the right amount, or split a bill between friends. It all happens in your browser tab: what you type is not sent anywhere.'),
                'features' => [__('QR codes for several bank and wallet formats'), __('See who a code pays before you share it'), __('Split a bill by person, item, tax and tip'), __('Saved payment methods, only if you choose to')],
                'cta' => __('Make a payment QR'),
                'steps' => [
                    [__('Pick a format'), __('Choose the payment scheme and fill in the details.')],
                    [__('Check the code'), __('PayHop shows who the code pays and how much before you download it.')],
                    [__('Share or split'), __('Download the QR, or divide a bill and give each person their own code.')],
                ],
                'faqs' => [
                    [__('Does PayHop move money?'), __('No. PayHop only makes the QR code. Your bank or wallet app makes the payment.')],
                    [__('Are my payment details uploaded?'), __('No. Codes are made in your browser tab. If you sign in and choose to save a payment method, it is stored encrypted and you can delete it at any time.')],
                    [__('Do I need a HOP ID to use PayHop?'), __('No. Every tool works without an account. A HOP ID only adds saved payment methods and history.')],
                ],
            ],
        ];
        $out = [];
        foreach ($manifest as $slug => $app) {
            if (! ($app['public'] ?? false) || ! isset($text[$slug])) {
                continue;
            }
            $out[$slug] = $text[$slug] + [
                'slug' => $slug,
                'name' => $app['name'],
                'url' => $app['url'],
                'public_prefix' => $app['public_prefix'] ?? $app['url'],
                'host' => parse_url($app['url'], PHP_URL_HOST),
                'mark' => $app['icons']['mark']['url'],
                'color' => $app['color'] ?? '#6366f1',
                'repo' => $app['repo'] ?? null,
                'screenshot' => '/images/apps/'.$slug.'.webp',
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
