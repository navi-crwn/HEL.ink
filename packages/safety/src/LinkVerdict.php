<?php

namespace Hop\Safety;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The one answer the apps act on for a link: allow, interstitial or block. The link is followed through its
 * redirects (each hop checked by UrlGuard and fetched without its body), the address it lands on is scored by
 * LinkSafety::assess() together with the address that was submitted, and the signals only this engine can see are
 * added: the length of the redirect chain, a destination that could not be verified, an executable Content-Type,
 * and who made the link (a guest pays a penalty, and a guest's link to a bare IP address is refused). The score is
 * the sum of safety.weights for every signal found, and safety.verdict.thresholds turn it into the verdict.
 */
class LinkVerdict
{
    public function __construct(
        private LinkSafety $safety,
        private UrlGuard $guard,
    ) {}

    /**
     * Judge a link.
     *
     * @param  string  $url  The destination as submitted.
     * @param  bool  $guest  True when the link was (or is being) made without an account.
     * @param  string  $text  Text around the link, such as a title, scored like the address.
     * @param  bool  $unroll  Follow the redirects and read the final headers. False judges the address alone (no
     *                        network at all), for example in a scan over stored links.
     * @param  bool  $remote  Ask the online providers (Safe Browsing, URLhaus, Spamhaus) about the final address.
     */
    public function evaluate(string $url, bool $guest = false, string $text = '', bool $unroll = true, bool $remote = true): Verdict
    {
        $url = trim($url);
        $scheme = $this->safety->scheme($url);
        if ($scheme === null || ! in_array($scheme, LinkSafety::WEB_SCHEMES, true) || $this->safety->host($url) === null) {
            return $this->decide(['invalid_url'], $guest, $url, 0);
        }

        $finalUrl = $url;
        $hops = 0;
        $contentType = null;
        $resolved = true;
        if ($unroll) {
            [$finalUrl, $hops, $contentType, $resolved] = $this->unroll($url);
        }

        $signals = $this->safety->assess($finalUrl, $text, remote: $remote)['signals'];
        // A provider that could not be asked is not a reason to allow: the link is unverified, not safe.
        if ($remote && config('safety.verdict.unverified_signal', true)) {
            foreach ($this->safety->providerStatus($finalUrl) as $provider => $status) {
                if ($status === 'unknown' && $provider === 'safe_browsing' && ! empty(config('safety.providers.safe_browsing_key'))) {
                    $signals[] = 'unverified_destination';
                }
            }
        }
        if ($finalUrl !== $url) {
            $signals = array_merge($signals, $this->safety->assess($url, '', remote: false)['signals']);
        }
        if ($hops >= (int) config('safety.verdict.chain_hops', 3)) {
            $signals[] = 'redirect_chain';
        }
        if (! $resolved) {
            $signals[] = 'unresolved_destination';
        }
        if ($contentType !== null && $this->isExecutableType($contentType)) {
            $signals[] = 'executable_download';
        }

        return $this->decide($signals, $guest, $finalUrl, $hops);
    }

    /**
     * @param  array<int, string>  $signals
     */
    private function decide(array $signals, bool $guest, string $finalUrl, int $hops): Verdict
    {
        if ($guest) {
            $signals[] = 'guest';
            if (in_array('ip_host', $signals, true)) {
                $signals[] = 'guest_ip_host';
            }
        }
        $reasons = array_values(array_unique($signals));
        $weights = config('safety.weights', []);
        $score = (int) array_sum(array_map(fn (string $s) => (int) ($weights[$s] ?? 0), $reasons));
        $thresholds = config('safety.verdict.thresholds', []);
        $verdict = match (true) {
            $score >= (int) ($thresholds['block'] ?? 70) => Verdict::BLOCK,
            $score >= (int) ($thresholds['interstitial'] ?? 30) => Verdict::INTERSTITIAL,
            default => Verdict::ALLOW,
        };

        return new Verdict($verdict, $score, $reasons, $finalUrl, $hops);
    }

    /**
     * Follow the link hop by hop. Every address is checked by UrlGuard before it is fetched (a private or local
     * target, or one with no DNS answer, ends the chain unfetched), and the fetch is pinned to the addresses that
     * were checked. The chain ends at the first non-redirect answer.
     *
     * @return array{0: string, 1: int, 2: string|null, 3: bool} final url, hops, final Content-Type, whether the
     *                                                           destination was reached with a non-error status
     */
    private function unroll(string $url): array
    {
        $maxHops = (int) config('safety.verdict.max_hops', 5);
        $current = $url;
        $hops = 0;
        while (true) {
            $target = $this->guard->resolve($current);
            if ($target === null) {
                return [$current, $hops, null, false];
            }
            $response = $this->fetch($current, $target);
            if ($response === null) {
                return [$current, $hops, null, false];
            }
            $location = (string) $response->header('Location');
            if ($response->redirect() && $location !== '') {
                if ($hops >= $maxHops) {
                    return [$current, $hops, null, false];
                }
                $current = $this->resolveLocation($current, $location);
                $hops++;

                continue;
            }
            if ($response->status() >= 400) {
                return [$current, $hops, null, false];
            }

            return [$current, $hops, $response->header('Content-Type') ?: null, true];
        }
    }

    /**
     * HEAD first; GET when the server refuses HEAD or fails it. The body is never read (the response is streamed
     * and dropped), only the status and the headers matter. Null when the request itself failed.
     *
     * @param  array{scheme: string, host: string, port: int, ips: array<int, string>}  $target
     */
    private function fetch(string $url, array $target): ?Response
    {
        if (! defined('CURLOPT_RESOLVE')) {
            // Without curl the connection cannot be pinned to the checked addresses, so nothing is fetched, in
            // any environment. Faking the Http client is no exemption: an unpinned fetch is a fetch.
            return null;
        }
        $timeout = (int) config('safety.verdict.timeout', 4);
        $request = function () use ($target, $timeout) {
            $request = Http::withOptions([
                'allow_redirects' => false,
                'stream' => true,
            ])->withUserAgent((string) config('safety.verdict.user_agent', 'Mozilla/5.0'))
                ->connectTimeout($timeout)
                ->timeout($timeout);
            $request->withOptions(['curl' => [CURLOPT_RESOLVE => $this->guard->curlResolve($target)]]);

            return $request;
        };
        try {
            $response = $request()->head($url);
            if ($response->status() < 400) {
                return $response;
            }
        } catch (\Throwable) {
            // Fall through to GET: some servers close a HEAD request outright.
        }
        try {
            return $request()->get($url);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * The address a Location header points to, when it is relative to the address that answered.
     */
    private function resolveLocation(string $base, string $location): string
    {
        $location = trim($location);
        if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $location)) {
            return $location;
        }
        $parts = parse_url($base) ?: [];
        $scheme = $parts['scheme'] ?? 'https';
        if (str_starts_with($location, '//')) {
            return $scheme.':'.$location;
        }
        $origin = $scheme.'://'.($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '');
        $path = $parts['path'] ?? '/';
        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }
        if (str_starts_with($location, '?') || str_starts_with($location, '#')) {
            return $origin.$path.$location;
        }
        $directory = rtrim(dirname($path), '/');

        return $origin.$directory.'/'.$location;
    }

    private function isExecutableType(string $contentType): bool
    {
        $type = strtolower(trim(explode(';', $contentType, 2)[0]));

        return in_array($type, array_map('strtolower', config('safety.verdict.executable_types', [])), true);
    }
}
