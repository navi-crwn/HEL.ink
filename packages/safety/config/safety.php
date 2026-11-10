<?php

/*
|--------------------------------------------------------------------------
| Abuse / spam detection
|--------------------------------------------------------------------------
|
| Used by App\Services\LinkSafety (checks at creation time) and the
| `abuse:scan` command (scheduled sweep). Categories listed in
| `block_categories` are rejected outright when a link is created; every
| category contributes `weights[category]` to a subject's score in scans.
|
*/

return [

    // Keyword patterns matched against URL + title/description text.
    'patterns' => [
        'csam' => '/\b(pthc|preteen|jailbait|lolicon|shotacon|pedophil\w*|hussyfan|babyj|opva|(child|kid)[\s_-]*porn|underage[\s_-]*(sex|porn|nude)|cp[\s_-]*(link|video|vid)s?)\b/i',
        'gambling' => '/(gacor|maxwin|togel|judi\s*online|sbobet|slot[\s_-]*(online|gacor|88|thailand|deposit|demo)|rtp[\s_-]*(live|slot)|toto[\s_-]*(macau|slot)|agen[\s_-]*(slot|togel|judi)|scatter[\s_-]*hitam|mahjong[\s_-]*ways)/i',
        'phishing' => '/(verify[-_\s]*account|account[-_\s]*verif|secure[-_\s]*login|wallet[-_\s]*connect|seed[-_\s]*phrase|claim[-_\s]*airdrop|airdrop[-_\s]*claim|dana[-_\s]*kaget|klaim[-_\s]*hadiah|verifikasi[-_\s]*(akun|rekening)|update[-_\s]*rekening'
            // Telegram account-takeover kits (2026-11): the page title, the form and the confirm button.
            .'|account[-_\s]*security[-_\s]*compliance|verification[-_\s]*code[-_\s]*and[-_\s]*two[-_\s]*step|two[-_\s]*step[-_\s]*password|yes,?[-_\s]*it[-_\s]*was[-_\s]*me\b|restore[-_\s]*(your[-_\s]*)?account[-_\s]*status)/i',
        'scam' => '/(double\s*your\s*(btc|crypto|money)|giveaway\s*(btc|eth|usdt)|investasi\s*(bodong|pasti\s*untung))/i',
        'adult' => '/\b(porn|xxx|bokep|ngentot|hentai|onlyfans\s*leak|colmek)\b/i',
    ],

    // Rejected at creation time and auto-actioned by abuse:scan.
    'block_categories' => ['csam', 'gambling', 'phishing', 'scam', 'malware', 'blacklisted', 'dangerous_scheme', 'encrypted_note_key', 'executable_download', 'tunnel_host'],

    // Hosts of NoteHop, whose links carry the note key in the #fragment (see EncryptedNoteLink). Add a self-hosted
    // install's domains with HOP_NOTE_HOSTS=note.example.com,n.example.com.
    'note_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('HOP_NOTE_HOSTS', 'note.hel.ink,n.hel.ink'))))),

    // Score contributed by each signal.
    'weights' => [
        'csam' => 100,
        'malware' => 100,
        'blacklisted' => 100,
        'dangerous_scheme' => 100,
        // Refused when saved; weight 0 so abuse:scan never disables a page or a person over it.
        'encrypted_note_key' => 0,
        // Hard blocks of the link verdict: an APK or Windows executable (by URL or by the final Content-Type) and a
        // temporary tunnel host (ngrok, Cloudflare quick tunnels, localtunnel and the like).
        'executable_download' => 100,
        'tunnel_host' => 100,
        // A bare IP address as the destination of a link made by a guest.
        'guest_ip_host' => 100,
        // Not a web address at all (no scheme, no host, or a scheme a browser would not open as a page).
        'invalid_url' => 100,
        'phishing' => 80,
        'gambling' => 60,
        'scam' => 60,
        // An Indonesian bank, wallet or institution name on a host or path that is not the official domain.
        'brand_spoof' => 55,
        // The final destination could not be verified: no DNS answer, a private address, a connection error, an
        // error status, or more than safety.verdict.max_hops redirects. Never a block on its own.
        'unresolved_destination' => 50,
        'adult' => 10,
        'chained_shortener' => 10,
        'file_host' => 20,
        // A machine-generated subdomain on an unknown domain: never enough to disable anything by itself.
        'random_subdomain' => 30,
        // A provider that was asked could not answer: unverified, never a block on its own.
        'unverified_destination' => 30,
        // The link redirects three times or more before it lands.
        'redirect_chain' => 25,
        // The link was made by a guest (no account), the verdict engine's guest penalty.
        'guest' => 25,
        'punycode' => 10,
        'ip_host' => 10,
        'risky_tld' => 30,
    ],

    // abuse:scan thresholds (per subject).
    'thresholds' => [
        'disable' => 60,       // deactivate the link / bio page
        'suspend_user' => 100, // owner's account is suspended
    ],

    // Owner is suspended when this many of their items are disabled in one scan.
    'suspend_after_disabled_items' => 3,

    // Items disabled for abuse are purged after this many days (never for csam).
    'purge_after_days' => 30,

    'shorteners' => ['bit.ly', 'tinyurl.com', 's.id', 'cutt.ly', 'shorturl.at', 'is.gd', 't.co', 'rebrand.ly', 'ow.ly', 'rb.gy', 'tiny.cc'],

    // Paste, anonymous file and video hosts often used to pass on abusive
    // material. A weak signal on its own; it adds up with other signals.
    'file_hosts' => [
        'justpaste.it', 'pastebin.com', 'paste.ee', 'ghostbin.com', 'rentry.co', 'rentry.org', 'privatebin.net',
        'zerobin.net', 'hastebin.com', 'dpaste.org', 'controlc.com', 'paste2.org', 'cl1p.net', '0bin.net', 'textbin.net',
        'telegra.ph', 'teletype.in', 'catbox.moe', 'litterbox.catbox.moe', 'anonfiles.com', 'anonfile.com', 'bayfiles.com',
        'mega.nz', 'mega.io', 'mediafire.com', 'zippyshare.com', 'file.io', 'gofile.io', 'sendspace.com', 'ufile.io',
        'uploadfiles.io', 'filepost.com', 'drop.sc', 'dropapk.to', 'dood.to', 'dood.la', 'dood.so', 'dood.watch',
        'doodstream.com', 'streamtape.com', 'mixdrop.co', 'upstream.to', 'streamlare.com', 'uqload.com', 'vidoza.net',
        // Video embed hosts common on pirated/adult re-uploads.
        'voe.sx', 'voe.to', 'filemoon.sx', 'filemoon.to', 'filemoon.in', 'streamwish.to', 'streamwish.com', 'wishembed.pro',
        'vidhide.com', 'vidhidepro.com', 'lulustream.com', 'luluvdo.com', 'd000d.com', 'd0000d.com', 'ds2play.com',
        'doods.pro', 'dood.yt', 'dood.re', 'dood.wf', 'mixdrop.to', 'mixdrop.sx', 'streamtape.to', 'vidguard.to',
        'mp4upload.com', 'vtube.to', 'streamvid.net', 'terabox.com', '1024terabox.com', 'teraboxapp.com',
        'krakenfiles.com', 'pixeldrain.com', 'bunkr.si', 'bunkr.ru', 'bunkrr.su', 'cyberdrop.me', 'saint.to', 'saint2.su',
    ],

    // Domains where a random-looking subdomain is normal (CDNs, object storage, the HOP family): the
    // random_subdomain signal never fires on them. Subdomains match (d1a2b3c4.cloudfront.net).
    'known_domains' => [
        'hel.ink',
        'cloudfront.net', 'amazonaws.com', 'akamaized.net', 'akamaihd.net', 'akamai.net', 'edgesuite.net', 'edgekey.net',
        'azureedge.net', 'windows.net', 'cloudflare.net', 'cloudflare.com', 'fastly.net', 'fastlylb.net',
        'googleusercontent.com', 'googleapis.com', 'gstatic.com', 'ggpht.com', 'googlevideo.com',
        'githubusercontent.com', 'fbcdn.net', 'cdninstagram.com', 'twimg.com', 'licdn.com', 'wp.com',
        'imgix.net', 'cloudinary.com', 'b-cdn.net', 'r2.dev', 'digitaloceanspaces.com', 'backblazeb2.com', 'cdn77.org',
    ],

    // High-risk TLDs (and public suffixes such as my.id), matched as the end of the host. One list for the
    // risky_tld signal of LinkSafety::assess() and the link verdict.
    'risky_tlds' => ['zip', 'mov', 'top', 'xyz', 'click', 'icu', 'cfd', 'sbs', 'rest', 'lol', 'buzz', 'cyou', 'monster', 'my.id', 'biz.id'],

    // Temporary tunnel services, where a page exists for an afternoon and no reputation list can know it. A link to
    // them (or to a subdomain) is a hard block.
    'tunnel_hosts' => ['ngrok-free.app', 'ngrok.io', 'ngrok.app', 'ngrok.dev', 'trycloudflare.com', 'loca.lt', 'serveo.net', 'localhost.run'],

    // Names of Indonesian banks, wallets and institutions that phishing pages borrow, matched in the host labels and
    // path segments (percent-decoded, lower case). A word of six characters or fewer only counts as a whole label or
    // segment (bni.example.com, /dana), so bandana or omnibus never match; a longer one also counts inside a label
    // (bankmandiri-login, telegramweb). The signal is brand_spoof.
    'brand_keywords' => [
        'klikbca', 'brimo', 'livin', 'mandiri', 'bni', 'bsi', 'dana', 'kaget', 'ovo', 'gopay', 'shopeepay',
        'telegram', 'whatsapp', 'kominfo', 'undian', 'blokir', 'tarif',
    ],

    // The real domains of those names. On them and on their subdomains brand_spoof never fires; a look-alike such
    // as klikbca.com.example.xyz or notklikbca.com is not one of them.
    'official_domains' => [
        'klikbca.com', 'bca.co.id', 'bri.co.id', 'bankmandiri.co.id', 'bni.co.id', 'bankbsi.co.id', 'dana.id',
        'ovo.id', 'gopay.co.id', 'shopee.co.id', 'telegram.org', 't.me', 'whatsapp.com', 'wa.me',
        'kominfo.go.id', 'komdigi.go.id',
    ],

    // The link verdict (LinkVerdict::evaluate()): the score of every signal above, plus the guest penalty, becomes
    // block (>= block), interstitial (>= interstitial) or allow.
    'verdict' => [
        'thresholds' => ['block' => 70, 'interstitial' => 30],
        // Redirects followed before the destination counts as unresolved. Three or more add redirect_chain.
        'max_hops' => 5,
        'chain_hops' => 3,
        // Seconds per hop, for connecting and for the headers.
        'timeout' => 4,
        // The link is fetched the way the victims' phones fetch it; kits often serve a harmless page to desktops.
        'user_agent' => 'Mozilla/5.0 (Linux; Android 14; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36',
        // Final Content-Type values that mean an installer or an executable, whatever the URL looks like.
        'executable_types' => ['application/vnd.android.package-archive', 'application/x-msdownload', 'application/x-msdos-program', 'application/x-ms-installer', 'application/x-dosexec'],
        // Add the unverified_destination signal when a provider that was asked could not answer (Safe Browsing down,
        // a timeout, a 5xx). Off means the link is judged without it, which reads as safe; keep it on.
        'unverified_signal' => (bool) env('VERDICT_UNVERIFIED_SIGNAL', true),
    ],

    // Optional external reputation lookups (skipped when the key is empty).
    'providers' => [
        'safe_browsing_key' => env('SAFE_BROWSING_KEY'),
        'urlhaus_auth_key' => env('URLHAUS_AUTH_KEY'),
        // DNS lookup against dbl.spamhaus.org; needs a non-public DNS resolver on the server.
        'spamhaus_dbl' => (bool) env('SPAMHAUS_DBL', true),
        'timeout' => 5,
        'cache_hours' => 12,
        // Safe Browsing v5 (Hop\Safety\SafeBrowsing): block/warn data is never trusted for longer than this, whatever
        // cacheDuration the provider asks for; a failed lookup is remembered only this long so a recovered provider
        // is asked again soon. A lookup sends four-byte SHA-256 prefixes, never the address.
        'threat_cache_minutes' => max(1, (int) env('SAFE_BROWSING_THREAT_CACHE_MINUTES', 30)),
        'unknown_cache_minutes' => max(1, (int) env('SAFE_BROWSING_UNKNOWN_CACHE_MINUTES', 5)),
    ],

    // Largest threat feed (abuse:feeds) accepted, in bytes. A bigger download is dropped and the previous feed kept.
    'feed_max_bytes' => (int) env('THREAT_FEED_MAX_BYTES', 64 * 1024 * 1024),

    // Registrations allowed per IP per hour.
    'register_per_ip_per_hour' => (int) env('ABUSE_REGISTER_PER_HOUR', 3),
];
