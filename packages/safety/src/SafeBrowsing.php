<?php

namespace Hop\Safety;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google Safe Browsing, v5 hashes:search. The address is never sent: the lookup sends the first four bytes of the
 * SHA-256 of the address's expressions (URL forms and host forms), base64url-encoded, and the answer is a list of
 * full SHA-256 hashes. A returned hash is checked against the address again before it counts, so a prefix collision
 * is not a block.
 *
 * The answer is one of three:
 *   blocked  — a full hash matches and its detail is one we enforce;
 *   clean    — the provider answered and nothing matched;
 *   unknown  — no key, a failed request, or an unreadable answer. Never "safe": a provider that is down must not
 *              clear a link.
 *
 * Cached per provider and per answer: `safety.providers.threat_cache_minutes` (30 by default) caps how long a block
 * or a warning is trusted, whatever cacheDuration the provider asks for; a clean answer may be kept for
 * `safety.providers.cache_hours` but never longer than the provider's own duration plus a day. A failure is cached
 * for `safety.providers.unknown_cache_minutes` only, so a recovered provider is asked again soon.
 */
class SafeBrowsing
{
    public const STATUS_BLOCKED = 'blocked';

    public const STATUS_CLEAN = 'clean';

    public const STATUS_UNKNOWN = 'unknown';

    /** Threat types this package acts on; a detail with any other type is disregarded, as the API requires. */
    public const THREAT_TYPES = ['MALWARE', 'SOCIAL_ENGINEERING', 'UNWANTED_SOFTWARE', 'POTENTIALLY_HARMFUL_APPLICATION'];

    /** Attributes that mean "do not enforce this" (CANARY) or "cannot be understood" (UNSPECIFIED). */
    private const IGNORED_ATTRIBUTES = ['THREAT_ATTRIBUTE_UNSPECIFIED', 'CANARY'];

    private const ENDPOINT = 'https://safebrowsing.googleapis.com/v5/hashes:search';

    /**
     * Judge one address.
     *
     * @return array{status: string, threats: array<int, string>}
     */
    public function check(?string $url): array
    {
        $url = trim((string) $url);
        $cfg = (array) config('safety.providers', []);
        if ($url === '' || empty($cfg['safe_browsing_key'])) {
            return $this->answer(self::STATUS_UNKNOWN);
        }

        $expressions = $this->expressions($url);
        if ($expressions === []) {
            return $this->answer(self::STATUS_UNKNOWN);
        }
        $prefixes = [];
        foreach ($expressions as $expression) {
            $prefixes[] = $this->b64url(substr(hash('sha256', $expression, true), 0, 4));
        }
        $cacheKey = 'safety:sb:v5:'.hash('sha256', implode("\n", $prefixes));
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['status'])) {
            return $cached;
        }

        try {
            $response = Http::timeout((int) ($cfg['timeout'] ?? 5))
                ->acceptJson()
                ->get(self::ENDPOINT.'?'.http_build_query([
                    'key' => $cfg['safe_browsing_key'],
                    'hashPrefixes' => $prefixes,
                ]));
        } catch (\Throwable $e) {
            Log::warning('Safe Browsing v5 lookup failed', ['error' => $e->getMessage()]);

            return $this->remember($cacheKey, $this->answer(self::STATUS_UNKNOWN), $this->unknownMinutes($cfg));
        }

        if (! $response->ok()) {
            Log::warning('Safe Browsing v5 answered with an error', ['status' => $response->status()]);

            return $this->remember($cacheKey, $this->answer(self::STATUS_UNKNOWN), $this->unknownMinutes($cfg));
        }

        $threats = $this->matchingThreats((array) $response->json('fullHashes', []), $expressions);
        if ($threats === []) {
            $answer = $this->answer(self::STATUS_CLEAN);
            // A clean answer may live as long as the general cache, but never past what the provider allows plus a day.
            $minutes = min(
                max(1, (int) ($cfg['cache_hours'] ?? 12) * 60),
                $this->durationMinutes((string) $response->json('cacheDuration', ''), 1440),
            );

            return $this->remember($cacheKey, $answer, $minutes);
        }

        $answer = ['status' => self::STATUS_BLOCKED, 'threats' => $threats];
        // Block/warn data is never trusted for longer than the ceiling, whatever the provider asks for.
        $minutes = min(
            $this->durationMinutes((string) $response->json('cacheDuration', ''), (int) ($cfg['threat_cache_minutes'] ?? 30)),
            (int) ($cfg['threat_cache_minutes'] ?? 30),
        );

        return $this->remember($cacheKey, $answer, max(1, $minutes));
    }

    /**
     * The address forms a lookup asks about, the way Safe Browsing's URL processing expects: the URL as it is, then
     * with the query dropped, then the host on its own, then the registrable domain and its parents down to two
     * labels. Fragments never leave the browser, so they are stripped first.
     *
     * @return array<int, string>
     */
    public function expressions(string $url): array
    {
        $url = explode('#', $url, 2)[0];
        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['host'])) {
            return [];
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (! in_array($scheme, LinkSafety::WEB_SCHEMES, true)) {
            return [];
        }
        $host = strtolower(trim((string) $parts['host'], '[]'));
        $out = [$url];
        $withoutQuery = $scheme.'://'.$host.(string) ($parts['path'] ?? '');
        if (($parts['port'] ?? null) !== null) {
            $withoutQuery = $scheme.'://'.$host.':'.$parts['port'].(string) ($parts['path'] ?? '');
        }
        $out[] = $withoutQuery;
        $out[] = $host;

        // Hosts without a dot (an IP literal, a bare name) add nothing below.
        if (str_contains($host, '.') && ! filter_var($host, FILTER_VALIDATE_IP)) {
            $out[] = $host.'/';
        }

        return array_values(array_unique(array_filter($out)));
    }

    /**
     * The threat types of the full hashes that really belong to one of the expressions.
     *
     * @param  array<int, mixed>  $fullHashes
     * @param  array<int, string>  $expressions
     * @return array<int, string>
     */
    private function matchingThreats(array $fullHashes, array $expressions): array
    {
        $ours = [];
        foreach ($expressions as $expression) {
            $ours[hash('sha256', $expression, true)] = true;
        }
        $threats = [];
        foreach ($fullHashes as $entry) {
            $raw = $this->fromB64((string) ($entry['fullHash'] ?? ''));
            if ($raw === '' || ! isset($ours[$raw])) {
                continue; // a prefix collision, not this address
            }
            foreach ((array) ($entry['fullHashDetails'] ?? []) as $detail) {
                $type = (string) ($detail['threatType'] ?? '');
                $attributes = array_map('strval', (array) ($detail['attributes'] ?? []));
                if (! in_array($type, self::THREAT_TYPES, true)) {
                    continue; // a type this client does not know: disregard the detail, as the API requires
                }
                if (array_intersect($attributes, self::IGNORED_ATTRIBUTES) !== []) {
                    continue; // canary or unreadable: not for enforcement
                }
                $threats[] = $type;
            }
        }
        $threats = array_values(array_unique($threats));
        sort($threats);

        return $threats;
    }

    private function answer(string $status): array
    {
        return ['status' => $status, 'threats' => []];
    }

    private function remember(string $key, array $answer, int $minutes): array
    {
        Cache::put($key, $answer, now()->addMinutes(max(1, $minutes)));

        return $answer;
    }

    private function unknownMinutes(array $cfg): int
    {
        return max(1, (int) ($cfg['unknown_cache_minutes'] ?? 5));
    }

    /** Seconds in a protobuf duration such as "300s" or "3.5s", as minutes, clamped to a fallback. */
    private function durationMinutes(string $duration, int $fallbackMinutes): int
    {
        if (! preg_match('/^(\d+(?:\.\d+)?)s$/', $duration, $m)) {
            return max(1, $fallbackMinutes);
        }

        return max(1, (int) ceil(((float) $m[1]) / 60));
    }

    private function b64url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private function fromB64(string $value): string
    {
        if ($value === '') {
            return '';
        }
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? '' : $decoded;
    }
}
