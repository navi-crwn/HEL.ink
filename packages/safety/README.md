# hop/safety

**English** · [简体中文](README.zh-CN.md) · [Deutsch](README.de.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md) · [Português (Brasil)](README.pt-BR.md) · [Español](README.es.md) · [Français](README.fr.md) · [Русский](README.ru.md)

The URL and content safety rules for the HOP family ([hel.ink](https://hel.ink)), as one Laravel package. LinkHop, BioHop and the hub use it, so a link or a bio page is judged the same way in every app.

## What it provides

| Class | What it does |
| --- | --- |
| `Hop\Safety\LinkSafety` | Judges a destination URL (and the text around it) against keyword patterns, risky hosts, brand spoofing, the shared domain blacklist, local threat feeds and optional online lookups. |
| `Hop\Safety\LinkVerdict` | Follows a link through its redirects and turns the score into one answer, a `Verdict`: `allow`, `interstitial` or `block`, with the reasons, the final address and the hop count. |
| `Hop\Safety\EncryptedNoteLink` | Recognises an encrypted NoteHop address, whose key sits in the `#fragment`, so an app that stores destinations can refuse it. `LinkSafety` uses it. |
| `Hop\Safety\UrlGuard` | Decides whether the server may fetch a URL: http or https only, and every address the host resolves to must be public. It can also pin the connection to the addresses it checked. |
| `Hop\Safety\SafeContent` | Cleans user content before it is shown: sanitizes HTML, turns an unsafe link into `#`, and strips what could break out of a `<style>` element. |
| `Hop\Safety\Models\DomainBlacklist` | The Eloquent model of the `domain_blacklist` table. |
| `abuse:feeds` | Artisan command that downloads the URLhaus and OpenPhish feeds. |

## Requirements

- PHP 8.3, `ext-intl`, Laravel 12 (`illuminate/support` ^12.0) and `symfony/html-sanitizer` ^7.4. `ext-intl` is how `EncryptedNoteLink` reads a host the way a browser does.
- For the blacklist, a `domain_blacklist` table with `domain`, `match_type` (`exact` or `wildcard`) and `category`. LinkHop owns the table and the other apps only read it. Without the table `LinkSafety` only uses the list the package ships, `resources/domain_blacklist.json`, which has the same columns and is read next to the table.

## Installation

The package is not on Packagist and has no tagged release yet. Add the repository to the app's `composer.json` and require `dev-main`, pinning a commit if you want a fixed version:

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/navi-crwn/hop-safety.git"
    }
],
"require": {
    "hop/safety": "dev-main"
}
```

Then run:

```bash
composer update hop/safety
```

Laravel's package discovery registers `Hop\Safety\SafetyServiceProvider`. It merges the `safety` config, binds `LinkSafety` as a scoped instance (so the blacklist is loaded once per request or job) and registers `abuse:feeds` when running in the console. To change the config, publish it:

```bash
php artisan vendor:publish --tag=hop-safety-config
```

## Usage

### Checking a destination URL

`LinkSafety::rejectionReason()` returns a message you can show to the user, or `null` when the URL is allowed. The second argument is text found near the URL (a title or description), the third is the list of allowed schemes. It defaults to `LinkSafety::WEB_SCHEMES` (`http`, `https`); `LinkSafety::BIO_SCHEMES` adds `mailto`, `tel` and `sms`.

```php
use Hop\Safety\LinkSafety;

$reason = app(LinkSafety::class)->rejectionReason($url, $title);
if ($reason !== null) {
    return back()->withErrors(['url' => $reason]);
}
```

A URL is rejected when its scheme is not allowed, when its host is missing, when it is an encrypted NoteHop address (see below), or when it matches a category listed in `safety.block_categories`. This call also asks the online providers (see below).

`assess()` gives the detail instead of a verdict: a score and the list of signals found. It skips the online providers unless you pass `remote: true`.

```php
$result = app(LinkSafety::class)->assess($url, $text);
// for example ['score' => 110, 'signals' => ['gambling', 'chained_shortener', 'executable_download']]
```

The signals are `csam`, `gambling`, `phishing`, `scam` and `adult` (keyword patterns), `dangerous_scheme` (`javascript:`, `data:`, `vbscript:`, `file:`, `blob:`), `ip_host`, `punycode`, `chained_shortener`, `file_host`, `risky_tld`, `tunnel_host`, `brand_spoof`, `random_subdomain`, `executable_download`, `blacklisted` (plus the blacklist row's own category), `malware` and `phishing` from the feeds, and `malware` from the online providers. Each signal adds its weight from `safety.weights` to the score.

- `risky_tld`: the host ends in one of `safety.risky_tlds`, which may be a public suffix of more than one label (`my.id`, `biz.id`).
- `tunnel_host`: the host is one of `safety.tunnel_hosts` or a subdomain of one (ngrok, Cloudflare quick tunnels, localtunnel, serveo, localhost.run). A hard block: a page on them lives for an afternoon and no reputation list can know it.
- `brand_spoof`: a host label or path segment carries the name of an Indonesian bank, wallet or institution from `safety.brand_keywords` (`klikbca`, `brimo`, `livin`, `mandiri`, `bni`, `bsi`, `dana`, `kaget`, `ovo`, `gopay`, `shopeepay`, `telegram`, `whatsapp`, `kominfo`, `undian`, `blokir`, `tarif`), and the host is not one of `safety.official_domains` or a subdomain of one. A name of six characters or fewer only matches a whole label or segment (`bni.example.com`, `/dana`), so `bandana.com` or `omnibus` never match; a longer name also matches inside one (`bankmandiri-login`, `telegramweb`). The path is read percent-decoded. The official list (`klikbca.com`, `bca.co.id`, `bri.co.id`, `bankmandiri.co.id`, `bni.co.id`, `bankbsi.co.id`, `dana.id`, `ovo.id`, `gopay.co.id`, `shopee.co.id`, `telegram.org`, `t.me`, `whatsapp.com`, `wa.me`, `kominfo.go.id`, `komdigi.go.id`) covers the domain and its subdomains only: `klikbca.com.example.xyz` and `notklikbca.com` are spoofs.
- `executable_download`: the address ends in `.apk`, `.exe`, `.scr`, `.msi`, `.bat`, `.cmd`, `.ps1`, `.vbs` or `.jar`. A hard block.

### The link verdict

`LinkVerdict::evaluate()` is the one answer the apps act on when a visitor follows a link: send them on, show a warning page first, or refuse. It follows the link through its redirects, scores the address it lands on (and the one that was submitted) with `LinkSafety::assess()`, adds what only the fetch can tell, and turns the total into a verdict.

```php
use Hop\Safety\LinkVerdict;
use Hop\Safety\Verdict;

$verdict = app(LinkVerdict::class)->evaluate($url, guest: $link->user_id === null, text: $link->title);

if ($verdict->blocks()) {
    abort(451);
}
if ($verdict->needsInterstitial()) {
    return view('interstitial', ['destination' => $verdict->finalUrl, 'reasons' => $verdict->reasons]);
}
return redirect()->away($verdict->finalUrl);
```

`Verdict` is a value object:

| Property | Meaning |
| --- | --- |
| `verdict` | `Verdict::ALLOW` (`allow`), `Verdict::INTERSTITIAL` (`interstitial`) or `Verdict::BLOCK` (`block`). `allows()`, `needsInterstitial()` and `blocks()` say the same. |
| `score` | The sum of `safety.weights` for every reason. |
| `reasons` | The signals found, once each, as keys for the app to translate (`blacklisted`, `brand_spoof`, `redirect_chain`, `guest`, ...), never prose. |
| `finalUrl` | The address the link lands on after its redirects, or the submitted address when nothing was followed. |
| `hops` | How many redirects were followed. |

`toArray()` gives the same with `final_url` as the key, for a JSON answer or a log line.

The arguments: `guest` is true when the link was made without an account; `text` is the title or description around the link; `unroll: false` skips the network entirely and judges the address alone (for a check at creation time or a scan over stored links); `remote: false` skips the online providers.

How the link is followed:

- Every hop goes through `UrlGuard::resolve()` first and the request is pinned to the addresses it checked. A hop whose host has no DNS answer, or resolves to a private or local address, is never fetched and ends the chain as unresolved.
- Each hop is a `HEAD` request, and a `GET` when the server refuses `HEAD` or the request fails. The body is never read: the response is streamed and only the status and headers are used. Timeout `safety.verdict.timeout` (4 s) per hop, with the mobile User-Agent of `safety.verdict.user_agent`, because kits often serve a harmless page to a desktop browser.
- Up to `safety.verdict.max_hops` (5) redirects are followed; relative `Location` headers are resolved against the hop that sent them. More than that, a transport error, or an error status (4xx, 5xx) on the final hop count as unresolved.

The signals only the verdict adds:

| Reason | Weight | When |
| --- | --- | --- |
| `redirect_chain` | 25 | `safety.verdict.chain_hops` (3) or more redirects. |
| `unresolved_destination` | 50 | The destination could not be verified (see above). Not a block on its own. |
| `executable_download` | 100 | The final `Content-Type` is one of `safety.verdict.executable_types` (an Android package, a Windows executable or installer), whatever the address looks like. |
| `guest` | 25 | The link was made by a guest. |
| `guest_ip_host` | 100 | A guest's link whose host is a bare IP address. |
| `invalid_url` | 100 | Not a web address: no scheme, no host, or a scheme other than `http` and `https`. Nothing is fetched. |

The thresholds are `safety.verdict.thresholds`: a score of 70 or more is `block`, 30 or more is `interstitial`, anything lower is `allow`. With the default weights a link made by a guest to a `.xyz` domain is an interstitial (30 + 25), a bank name on an unofficial host under a risky TLD is a block for everyone (55 + 30), and an `.apk`, a tunnel host, a blacklisted domain or a guest's link to a bare IP is a block on its own.

`LinkVerdict` is bound as a scoped instance, like `LinkSafety`. It does not cache; the apps decide when to re-check a stored link.

### Encrypted NoteHop links

A NoteHop link keeps the note key in the fragment (`https://n.hel.ink/TOKEN#k=KEY`, and `&a=` for the owner link). Browsers never send a fragment to a server, which is why NoteHop cannot read the note. An app that stores such a link as a destination, a bio link or a report would hold the key in its database, its logs and its redirects. `rejectionReason()` therefore refuses a NoteHop address whose fragment has a `k` or `a` parameter with a value, in a URL and in free text, and `assess()` reports it as the `encrypted_note_key` signal (weight 0, it is refused when saved and never scored).

The address is read the way a browser reads it, not the way `parse_url()` does: tabs and line breaks dropped, control characters trimmed, any number of slashes after `https:`, a backslash ending the host, the last `@` ending the user info, percent escapes, full-width letters, `U+3002`, soft hyphens and a trailing dot all reach the host the browser would connect to, and `#?k=KEY` is the `k` parameter because `URLSearchParams` drops one `?`. An Android `intent://` address is searched for a NoteHop fallback address. Other hosts, other fragments and `?k=` in the query (which NoteHop does not read) are ordinary destinations.

The hosts are `safety.note_hosts` (`HOP_NOTE_HOSTS`, default `note.hel.ink,n.hel.ink`). An app can add more, for example from its app registry, with `EncryptedNoteLink::resolveHostsUsing(fn () => [...])`.

The same class helps apps clean what they already hold: `withoutFragment($url)`, `withoutSecrets($url)` (only `k` and `a` leave the fragment), and `scrub($text)` and `scrubJson($json)` (every NoteHop address in a text or a JSON column; other hosts' fragments stay).

### Fetching a user-supplied URL

`UrlGuard::isSafe($url)` answers whether a URL may be fetched from the server. It accepts only `http` and `https`, resolves the host, and refuses the URL if any resolved address is private or reserved. That covers loopback, link-local (including cloud metadata addresses such as `169.254.169.254`) and the private ranges.

`isSafe()` only gives a yes or no. If you then hand the URL to an HTTP client, the client looks the host up again, and a hostile DNS server can answer differently the second time (DNS rebinding). To close that gap, call `resolve()` once and make the client connect to the addresses that were checked:

```php
use Hop\Safety\UrlGuard;
use Illuminate\Support\Facades\Http;

$guard = app(UrlGuard::class);
$target = $guard->resolve($url);   // null when the URL is not safe
if ($target === null) {
    throw new RuntimeException('Address not allowed');
}
// ['scheme' => 'https', 'host' => 'example.com', 'port' => 443, 'ips' => ['93.184.216.34', ...]]

$response = Http::withOptions([
    'allow_redirects' => false,
    'curl' => [CURLOPT_RESOLVE => $guard->curlResolve($target)],
])->get($url);
```

`curlResolve()` returns a single `host:port:address1,address2` entry that lists every checked address. Follow redirects yourself and call `resolve()` again for every `Location`. `isSafe()` is `resolve() !== null`.

All addresses go into one entry because libcurl keeps only the last entry for a given host and port; with one entry per address the request would be pinned to the last address only, and fail on a machine without IPv6. With one entry, libcurl tries the checked addresses and the request never goes to an address that was not checked.

### Sanitizing user content

```php
use Hop\Safety\SafeContent;

$html = SafeContent::html($rawUserHtml);
$href = SafeContent::url($userUrl);          // '#' unless the scheme is allowed
$css  = SafeContent::css($userCss);
```

- `html()` keeps formatting tags and drops scripts, event handlers, styles, forms and links that are not `http`, `https`, `mailto` or `tel`. Links get `rel="nofollow noreferrer noopener"` and `target="_blank"`. Input is cut at 50,000 characters.
- `url()` returns a same-site path as is, and any other value only if its scheme is in the allowed list (by default `LinkSafety::BIO_SCHEMES`). Everything else becomes `#`.
- `css()` removes `<`, `@import`, `expression(`, `javascript:`, `behavior:` and `-moz-binding`, so custom CSS cannot close its `<style>` element or load scripts.
- `isSitePath()` tells a real same-site path from one a browser would read as another host, such as `/\evil.com`.

Apply these when rendering, not only when saving, so rows stored before a rule existed are covered.

### Threat feeds

```bash
php artisan abuse:feeds
```

The command downloads the URLhaus online malware list and the OpenPhish community feed into `storage/app/threat-feeds/malware.txt` and `phishing.txt`. `LinkSafety` matches a URL against them exactly (scheme and a leading `www.` ignored), so a shared platform is not blocked as a whole. LinkHop runs the command daily.

A feed is downloaded to a temporary file, checked and then moved over the old one. A failed, truncated, empty or non-URL download keeps the previous list. A download larger than `safety.feed_max_bytes` is aborted. The command fails only when every feed failed.

### Online lookups

When `rejectionReason()` runs (or `assess(..., remote: true)`), three optional providers are asked, and a hit adds the `malware` signal. Answers are cached for `safety.providers.cache_hours` (12). A provider that is unreachable or returns an error never blocks a URL.

- Spamhaus DBL over DNS, on by default (`SPAMHAUS_DBL`). It needs a DNS resolver that Spamhaus accepts, which public resolvers are not.
- Google Safe Browsing, used when `SAFE_BROWSING_KEY` is set. Only the address without its `#fragment` is sent (a fragment is never sent to a server, so it is not told to a third party either), and the cached answer is keyed the same way.
- URLhaus host lookup, used when `URLHAUS_AUTH_KEY` is set.

## Configuration

| Variable | Default | Meaning |
| --- | --- | --- |
| `SAFE_BROWSING_KEY` | none | Enables the Google Safe Browsing lookup. |
| `URLHAUS_AUTH_KEY` | none | Enables the URLhaus host lookup. |
| `SPAMHAUS_DBL` | `true` | Spamhaus DBL lookup over DNS. |
| `THREAT_FEED_MAX_BYTES` | `67108864` (64 MB) | Largest feed `abuse:feeds` accepts. |
| `HOP_NOTE_HOSTS` | `note.hel.ink,n.hel.ink` | Comma-separated NoteHop hosts whose links carry the key in the fragment (self-hosted installs add theirs). |
| `ABUSE_REGISTER_PER_HOUR` | `3` | Read by the apps, not by this package. |

The rest lives in `config/safety.php`: the keyword `patterns`, `block_categories`, the `weights` of each signal, the lists `shorteners`, `file_hosts`, `risky_tlds`, `tunnel_hosts`, `brand_keywords`, `official_domains` and `known_domains`, and the `verdict` settings (`thresholds`, `max_hops`, `chain_hops`, `timeout`, `user_agent`, `executable_types`). The keys `thresholds`, `suspend_after_disabled_items` and `purge_after_days` are read by the `abuse:scan` command of LinkHop and BioHop, not by this package.

## Tests

The tests in `tests/Feature` are Pest tests that run inside an app that uses the package (HEL.ink includes a copy in `packages/safety`). The tests in `tests/Unit` need no application. `tests/fixtures/note-link-variants.json` holds 64 spellings of a NoteHop address with the verdict of a WHATWG URL parser, produced by `tests/fixtures/note-link-truth.js` (Node). `tests/fixtures/verdict-cases.json` lists addresses with the verdict expected of them, among them the phishing hosts that reached LinkHop; the verdict tests fake DNS and HTTP and never touch the network. This repository's own CI checks that `composer.json` is valid, that every PHP file lints under PHP 8.3, and that the family table below matches the canonical one.

## Security

Report vulnerabilities as described in [SECURITY.md](SECURITY.md), not in public issues.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Self-hosting

You do not install this package by hand to run HOP. LinkHop and BioHop list this public repository in their `composer.json`, so `composer install` fetches it with no token, and the hub ([HEL.ink](https://github.com/navi-crwn/HEL.ink)) carries a copy in `packages/safety`. It works with no keys at all: Safe Browsing and URLhaus stay off until you set `SAFE_BROWSING_KEY` and `URLHAUS_AUTH_KEY`, the Spamhaus check is a DNS lookup that needs no key (`SPAMHAUS_DBL=false` turns it off), and without LinkHop's `domain_blacklist` table it uses the list it ships. If you run NoteHop, set `HOP_NOTE_HOSTS` to its host names, so encrypted note links on your domain are recognised; the default is the hel.ink ones. The family guide covers the rest: [docs/self-hosting.md](https://github.com/navi-crwn/HEL.ink/blob/main/docs/self-hosting.md).

## HOP family

<!-- hop-family:start -->
<!-- Generated from HEL.ink resources/hop-assets/apps.source.json; edit there, then run scripts/hop_assets.py build. -->
| App | Address | Repository | What it does |
| --- | --- | --- | --- |
| **HOP ID** | [hel.ink](https://hel.ink) · accounts at `hub.hel.ink` | [navi-crwn/HEL.ink](https://github.com/navi-crwn/HEL.ink) | One account for every HOP app. |
| **LinkHop** | [link.hel.ink](https://link.hel.ink) · `hel.ink/l/…` | [navi-crwn/LinkHop](https://github.com/navi-crwn/LinkHop) | Short links, QR codes and click analytics. |
| **BioHop** | [bio.hel.ink](https://bio.hel.ink) · `hel.ink/b/…` | [navi-crwn/BioHop](https://github.com/navi-crwn/BioHop) | One link-in-bio page for everything you do. |
| **PixelHop** | [pixel.hel.ink](https://pixel.hel.ink) · `p.hel.ink/…` | [navi-crwn/PixelHop](https://github.com/navi-crwn/PixelHop) | Image hosting with albums and direct links. |
| **NoteHop** | [note.hel.ink](https://note.hel.ink) · `n.hel.ink/…` | [navi-crwn/NoteHop](https://github.com/navi-crwn/NoteHop) | Encrypted notes you share with a link. |
| **FileHop** | [file.hel.ink](https://file.hel.ink) | [navi-crwn/FileHop](https://github.com/navi-crwn/FileHop) | Convert files in your browser; nothing is uploaded. |
| **PayHop** | [pay.hel.ink](https://pay.hel.ink) | [navi-crwn/PayHop](https://github.com/navi-crwn/PayHop) | Make a payment QR and split a bill; nothing leaves your browser. |
| **Imperium** | not public-facing: optional, for admins | [navi-crwn/Imperium](https://github.com/navi-crwn/Imperium) | The HOP control panel. |
<!-- hop-family:end -->

## License

The HOP shared packages are open-source software licensed under the [MIT license](LICENSE).
