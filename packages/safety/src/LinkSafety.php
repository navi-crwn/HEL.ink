<?php

namespace Hop\Safety;

use Hop\Safety\Console\UpdateThreatFeeds;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Decides whether a destination URL (plus its surrounding text) is safe to
 * host. Shared by request validation (creation time) and `abuse:scan`.
 */
class LinkSafety
{
    public const WEB_SCHEMES = ['http', 'https'];

    public const BIO_SCHEMES = ['http', 'https', 'mailto', 'tel', 'sms'];

    private const DANGEROUS_SCHEMES = ['javascript', 'data', 'vbscript', 'file', 'blob'];

    /** Domains the package blocks on its own, in the domain_blacklist table's row format. */
    public const BUNDLED_BLACKLIST = __DIR__.'/../resources/domain_blacklist.json';

    /** Second-level labels that are public suffixes under a country code (co.uk, com.au, co.id, or.id). */
    private const PUBLIC_SECOND_LEVELS = ['co', 'com', 'net', 'org', 'gov', 'edu', 'ac', 'or', 'go', 'web', 'my', 'biz', 'sch', 'mil', 'info', 'ne', 'id', 'in'];

    /** @var array{exact: array<string, string|null>, wildcard: array<int, array{0: string, 1: string|null}>}|null */
    private ?array $blacklist = null;

    /** @var array<string, array<string, int>>|null */
    private ?array $feeds = null;

    private ?EncryptedNoteLink $notes = null;

    private ?SafeBrowsing $browsing = null;

    /** Knows the address of an encrypted NoteHop note (its key is in the #fragment). Override to add hosts. */
    protected function notes(): EncryptedNoteLink
    {
        return $this->notes ??= new EncryptedNoteLink;
    }

    /**
     * Validation helper: returns a user-facing error, or null when allowed.
     *
     * @param  array<int, string>  $schemes
     */
    public function rejectionReason(?string $url, string $text = '', array $schemes = self::WEB_SCHEMES): ?string
    {
        // An encrypted NoteHop address (or free text holding one) keeps its key in the fragment: never stored here.
        if ($this->holdsNoteKey($url, $text)) {
            return $this->notes()->message();
        }
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }
        $scheme = $this->scheme($url);
        if ($scheme === null || ! in_array($scheme, $schemes, true)) {
            return 'This link type is not allowed.';
        }
        if (in_array($scheme, self::WEB_SCHEMES, true) && ! $this->host($url)) {
            return 'Invalid URL format. Please check the URL format.';
        }
        $result = $this->assess($url, $text, remote: true);
        $blocked = array_intersect($result['signals'], config('safety.block_categories', []));
        if ($blocked !== []) {
            return 'This destination is not allowed due to our security policy.';
        }

        return null;
    }

    /**
     * Score a URL + text. `signals` lists every matched category.
     *
     * @return array{score: int, signals: array<int, string>}
     */
    public function assess(?string $url, string $text = '', bool $remote = false): array
    {
        $signals = [];
        // The address is matched as typed and as a person reads it (path and query decoded), so a phrase hidden as
        // /account-security-compliance or ?t=yes%2C+it+was+me is seen the same way as in a page title.
        $haystack = trim($text.' '.$url.' '.$this->readableUrl((string) $url));
        foreach (config('safety.patterns', []) as $category => $pattern) {
            if ($haystack !== '' && preg_match($pattern, $haystack)) {
                $signals[] = $category;
            }
        }
        if ($this->holdsNoteKey($url, $text)) {
            $signals[] = 'encrypted_note_key';
        }
        $url = trim((string) $url);
        // A fragment never reaches the destination server, so it is not sent to outside lookups either.
        $remoteUrl = explode('#', $url, 2)[0];
        if ($url !== '') {
            $scheme = $this->scheme($url);
            if ($scheme !== null && in_array($scheme, self::DANGEROUS_SCHEMES, true)) {
                $signals[] = 'dangerous_scheme';
            }
            $host = $this->host($url);
            if ($host) {
                if (filter_var($host, FILTER_VALIDATE_IP)) {
                    $signals[] = 'ip_host';
                }
                if (str_contains($host, 'xn--')) {
                    $signals[] = 'punycode';
                }
                if ($this->hostInList($host, config('safety.shorteners', []))) {
                    $signals[] = 'chained_shortener';
                }
                if ($this->hostInList($host, config('safety.file_hosts', []))) {
                    $signals[] = 'file_host';
                }
                if ($this->hasRiskyTld($host)) {
                    $signals[] = 'risky_tld';
                }
                if ($this->hostInList($host, config('safety.tunnel_hosts', []))) {
                    $signals[] = 'tunnel_host';
                }
                if ($this->spoofsBrand($host, $url)) {
                    $signals[] = 'brand_spoof';
                }
                if ($this->hasRandomSubdomain($host)) {
                    $signals[] = 'random_subdomain';
                }
                $category = $this->blacklistCategory($host);
                if ($category !== false) {
                    $signals[] = 'blacklisted';
                    if ($category && array_key_exists($category, config('safety.weights', []))) {
                        $signals[] = $category;
                    }
                }
                if ($remote && in_array($scheme, self::WEB_SCHEMES, true) && $this->isKnownMalicious($remoteUrl, $host)) {
                    $signals[] = 'malware';
                }
            }
            if ($category = $this->feedCategory($url)) {
                $signals[] = $category;
            }
            if (preg_match('/\.(apk|exe|scr|msi|bat|cmd|ps1|vbs|jar)(\?|#|$)/i', $url)) {
                $signals[] = 'executable_download';
            }
        }
        $signals = array_values(array_unique($signals));
        $weights = config('safety.weights', []);
        $score = array_sum(array_map(fn ($s) => (int) ($weights[$s] ?? 0), $signals));

        return ['score' => $score, 'signals' => $signals];
    }

    /**
     * The path, query and fragment of an address the way a browser shows them: percent-decoded, '+' as a space.
     */
    private function readableUrl(string $url): string
    {
        $parts = parse_url(trim($url));
        if (! is_array($parts)) {
            return '';
        }

        return urldecode(trim(($parts['path'] ?? '').' '.($parts['query'] ?? '').' '.($parts['fragment'] ?? '')));
    }

    /**
     * True when the host ends in one of safety.risky_tlds, which may be a public suffix of more than one label
     * (my.id). A label of the same name elsewhere in the host (xyz.example.com) does not count.
     */
    private function hasRiskyTld(string $host): bool
    {
        foreach (config('safety.risky_tlds', []) as $tld) {
            if (str_ends_with($host, '.'.$tld)) {
                return true;
            }
        }

        return false;
    }

    /**
     * True when the host or the path carries the name of an Indonesian bank, wallet or institution
     * (safety.brand_keywords) and the host is not that institution's domain or one of its subdomains
     * (safety.official_domains). Labels and segments are compared whole; a name of seven characters or more also
     * matches inside one (bankmandiri-login), a shorter one never does (bandana is not dana).
     */
    private function spoofsBrand(string $host, string $url): bool
    {
        $keywords = config('safety.brand_keywords', []);
        if ($keywords === [] || $this->hostInList($host, config('safety.official_domains', []))) {
            return false;
        }
        $path = parse_url($url, PHP_URL_PATH);
        $tokens = preg_split('/[^a-z0-9]+/', strtolower($host.'/'.urldecode(is_string($path) ? $path : '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($keywords as $keyword) {
            $keyword = strtolower($keyword);
            foreach ($tokens as $token) {
                if ($token === $keyword || (strlen($keyword) >= 7 && str_contains($token, $keyword))) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * True when a label left of the registrable domain looks machine-generated: phishing kits put every victim on a
     * throwaway subdomain (mjdgdqgk.example.com). Domains where such labels are normal (CDNs, object storage, the
     * HOP family) are listed in safety.known_domains and never count.
     */
    private function hasRandomSubdomain(string $host): bool
    {
        if (filter_var($host, FILTER_VALIDATE_IP) || $this->hostInList($host, config('safety.known_domains', []))) {
            return false;
        }
        $labels = explode('.', $host);
        $keep = $this->registrableLabels($labels);
        foreach (array_slice($labels, 0, max(0, count($labels) - $keep)) as $label) {
            if ($this->looksRandom($label)) {
                return true;
            }
        }

        return false;
    }

    /**
     * How many trailing labels make up the registrable domain: two (example.com), three under a public second-level
     * suffix of a country code (example.co.uk, example.co.id).
     *
     * @param  array<int, string>  $labels
     */
    private function registrableLabels(array $labels): int
    {
        $n = count($labels);
        if ($n < 3) {
            return $n;
        }
        $tld = $labels[$n - 1];
        $second = $labels[$n - 2];

        return strlen($tld) === 2 && in_array($second, self::PUBLIC_SECOND_LEVELS, true) ? 3 : 2;
    }

    /**
     * A label of seven or more letters and digits whose letters are almost all consonants (at most one vowel in
     * five) reads as random: mjdgdqgk, xkpqtrvza, f3b9c2d7e1. Words, short labels and numbers do not; the odd
     * consonant-only word (strengths) does, which only adds the signal's weight.
     */
    private function looksRandom(string $label): bool
    {
        $label = strtolower($label);
        $alnum = preg_replace('/[^a-z0-9]/', '', $label) ?? '';
        $letters = preg_replace('/[^a-z]/', '', $alnum) ?? '';
        if (strlen($alnum) < 7 || strlen($letters) < 5) {
            return false;
        }
        $vowels = preg_match_all('/[aeiouy]/', $letters);

        return $vowels / strlen($letters) <= 0.2;
    }

    private function holdsNoteKey(?string $url, string $text): bool
    {
        return $this->notes()->matches($url) || $this->notes()->containsKey($url) || $this->notes()->containsKey($text);
    }

    /**
     * True when the host is one of the listed domains or a subdomain of one
     * (cdn.mediafire.com matches mediafire.com).
     */
    private function hostInList(string $host, array $domains): bool
    {
        foreach ($domains as $domain) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return true;
            }
        }

        return false;
    }

    public function host(?string $url): ?string
    {
        $host = parse_url(trim((string) $url), PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return null;
        }

        return preg_replace('/^www\./', '', strtolower(trim($host, '[]')));
    }

    public function scheme(?string $url): ?string
    {
        // Strip whitespace/control chars browsers ignore (e.g. "java\tscript:")
        $clean = preg_replace('/[\x00-\x20]+/', '', (string) $url);
        if (! preg_match('/^([a-z][a-z0-9+.\-]*):/i', $clean, $m)) {
            return null;
        }

        return strtolower($m[1]);
    }

    /**
     * Blacklist category for a host: string|null when listed, false when not.
     */
    public function blacklistCategory(string $host): string|false|null
    {
        $this->blacklist ??= $this->loadBlacklist();
        if (array_key_exists($host, $this->blacklist['exact'])) {
            return $this->blacklist['exact'][$host];
        }
        foreach ($this->blacklist['wildcard'] as [$pattern, $category]) {
            if (preg_match($pattern, $host)) {
                return $category;
            }
        }

        return false;
    }

    public function forgetBlacklist(): void
    {
        $this->blacklist = null;
    }

    /**
     * The bundled list (resources/domain_blacklist.json) plus the shared domain_blacklist table, when the app has it.
     */
    private function loadBlacklist(): array
    {
        $list = ['exact' => [], 'wildcard' => []];
        $rows = $this->bundledBlacklist();
        if (Schema::hasTable('domain_blacklist')) {
            $rows = array_merge($rows, DB::table('domain_blacklist')->get(['domain', 'match_type', 'category'])->all());
        }
        foreach ($rows as $row) {
            $domain = preg_replace('/^www\./', '', strtolower(trim($row->domain)));
            if ($row->match_type === 'wildcard') {
                $pattern = '/^'.str_replace(['.', '*'], ['\.', '.*'], $domain).'$/i';
                $list['wildcard'][] = [$pattern, $row->category];
            } else {
                $list['exact'][$domain] = $row->category;
            }
        }

        return $list;
    }

    /**
     * Rows shipped with the package, in the table's shape (domain, match_type, category). A broken file is logged and
     * ignored rather than taking every app down.
     *
     * @return array<int, object>
     */
    private function bundledBlacklist(): array
    {
        try {
            $rows = json_decode((string) file_get_contents(self::BUNDLED_BLACKLIST), flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            Log::error('hop/safety: bundled domain blacklist is unreadable', ['error' => $e->getMessage()]);

            return [];
        }

        return array_values(array_filter((array) $rows, fn ($row) => is_object($row) && isset($row->domain, $row->match_type)));
    }

    /**
     * Google Safe Browsing + URLhaus. Fails open: provider errors never block.
     */
    private function isKnownMalicious(string $url, string $host): bool
    {
        $cfg = config('safety.providers');
        if (empty($cfg['safe_browsing_key']) && empty($cfg['urlhaus_auth_key']) && empty($cfg['spamhaus_dbl'])) {
            return false;
        }

        // Each provider keeps its own answer and its own cache (Safe Browsing inside Hop\Safety\SafeBrowsing, the
        // other two here on the host), so a failed lookup at one provider never hides what another one knows.
        if ($this->spamhausHit($host, $cfg) || $this->safeBrowsingHit($url)) {
            return true;
        }

        return Cache::remember('abuse:remote:urlhaus:'.sha1($host), now()->addHours((int) ($cfg['cache_hours'] ?? 12)), function () use ($host, $cfg) {
            return $this->urlhausHit($host, $cfg);
        });
    }

    /**
     * What the outside providers said about this address, for a caller that must tell "nothing known" from "could not
     * ask": safe_browsing (blocked/clean/unknown), urlhaus (hit/miss/unknown) and spamhaus (hit/miss/unknown).
     *
     * @return array{safe_browsing: string, urlhaus: string, spamhaus: string}
     */
    public function providerStatus(?string $url): array
    {
        $cfg = (array) config('safety.providers', []);
        $url = trim((string) $url);
        $host = $this->host($url) ?? '';
        $sb = $this->browsing()->check($url)['status'];
        $urlhaus = empty($cfg['urlhaus_auth_key']) ? 'unknown' : ($this->urlhausHit($host, $cfg) ? 'hit' : 'miss');
        $spamhaus = (($cfg['spamhaus_dbl'] ?? false) && $host !== '') ? ($this->spamhausHit($host, $cfg) ? 'hit' : 'miss') : 'unknown';

        return ['safe_browsing' => $sb, 'urlhaus' => $urlhaus, 'spamhaus' => $spamhaus];
    }

    /**
     * Spamhaus DBL over DNS (free for low volume, no key). Only 127.0.1.2-99
     * (spam/phish/malware/botnet domains) count; 127.0.1.1xx means a legit domain
     * with abused content, and 127.255.255.x means our resolver is refused
     * (public resolvers are blocked) - both are ignored.
     */
    private function spamhausHit(string $host, array $cfg): bool
    {
        if (empty($cfg['spamhaus_dbl']) || filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }
        $records = @dns_get_record($host.'.dbl.spamhaus.org', DNS_A) ?: [];
        foreach ($records as $record) {
            if (preg_match('/^127\.0\.1\.(\d+)$/', $record['ip'] ?? '', $m) && (int) $m[1] >= 2 && (int) $m[1] < 100) {
                return true;
            }
        }

        return false;
    }

    /**
     * Exact-URL match against the local URLhaus/OpenPhish feeds (abuse:feeds).
     */
    private function feedCategory(string $url): ?string
    {
        $this->feeds ??= collect(array_keys(UpdateThreatFeeds::FEEDS))
            ->mapWithKeys(function ($category) {
                $file = storage_path("app/threat-feeds/{$category}.txt");

                return [$category => is_file($file) ? array_flip(explode("\n", file_get_contents($file))) : []];
            })->all();
        $needle = UpdateThreatFeeds::normalize($url);
        foreach ($this->feeds as $category => $set) {
            if (isset($set[$needle])) {
                return $category;
            }
        }

        return null;
    }

    /** Safe Browsing: v5 hashes:search, one instance so its answers are read once per request. */
    public function browsing(): SafeBrowsing
    {
        return $this->browsing ??= new SafeBrowsing;
    }

    /** True only when the provider answered and matched; an unknown answer is not a hit and not a clean bill either. */
    private function safeBrowsingHit(string $url): bool
    {
        return $this->browsing()->check($url)['status'] === SafeBrowsing::STATUS_BLOCKED;
    }

    private function urlhausHit(string $host, array $cfg): bool
    {
        if (empty($cfg['urlhaus_auth_key'])) {
            return false;
        }
        try {
            $response = Http::timeout($cfg['timeout'] ?? 5)
                ->withHeaders(['Auth-Key' => $cfg['urlhaus_auth_key']])
                ->asForm()
                ->post('https://urlhaus-api.abuse.ch/v1/host/', ['host' => $host]);
            if (! $response->ok() || $response->json('query_status') !== 'ok') {
                return false;
            }
            // Only count hosts with currently online malicious URLs.
            foreach ((array) $response->json('urls') as $entry) {
                if (($entry['url_status'] ?? null) === 'online') {
                    return true;
                }
            }

            return false;
        } catch (\Throwable $e) {
            Log::warning('URLhaus lookup failed', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
