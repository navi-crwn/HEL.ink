<?php

namespace Hop\Safety;

/**
 * Guards server-side (outbound) HTTP fetches against SSRF.
 *
 * A URL is only considered safe when it uses http/https and every IP its
 * host resolves to is a publicly routable address. This blocks requests to
 * loopback, private, link-local and other reserved ranges — including cloud
 * metadata endpoints such as 169.254.169.254.
 */
class UrlGuard
{
    /**
     * Determine whether the given URL is safe to fetch from the server.
     *
     * This only answers yes or no. A client that resolves the host again when
     * it connects can still be handed a different address (DNS rebinding); to
     * close that gap use resolve() and connect to the addresses it returns.
     */
    public function isSafe(?string $url): bool
    {
        return $this->resolve($url) !== null;
    }

    /**
     * Resolve and validate a URL once, and return the target to connect to.
     *
     * Returns null when the URL is not safe (see isSafe()). Otherwise the
     * array has the scheme, the host (lower case, without IPv6 brackets), the
     * port (the scheme's default when the URL has none) and the public IP
     * addresses the host resolved to. Connect to those addresses, not to the
     * host name again, for example with curlResolve() and CURLOPT_RESOLVE:
     *
     *     $target = $guard->resolve($url);
     *     if ($target === null) { abort(422); }
     *     Http::withOptions(['curl' => [CURLOPT_RESOLVE => $guard->curlResolve($target)]])->get($url);
     *
     * Redirects must be followed manually, calling resolve() for every hop.
     *
     * @return array{scheme: string, host: string, port: int, ips: array<int, string>}|null
     */
    public function resolve(?string $url): ?array
    {
        if (! is_string($url) || $url === '') {
            return null;
        }
        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }
        $scheme = strtolower($parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }
        $host = $parts['host'];
        // Strip IPv6 brackets, e.g. [::1]
        $host = trim($host, '[]');
        $ips = array_values(array_unique($this->resolveHost($host)));
        if ($ips === []) {
            return null;
        }
        foreach ($ips as $ip) {
            if (! $this->isPublicIp($ip)) {
                return null;
            }
        }

        return [
            'scheme' => $scheme,
            'host' => strtolower($host),
            'port' => (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80)),
            'ips' => $ips,
        ];
    }

    /**
     * The CURLOPT_RESOLVE entries ("host:port:address") that pin a connection
     * to the addresses resolve() validated.
     *
     * @param  array{host: string, port: int, ips: array<int, string>}  $target
     * @return array<int, string>
     */
    public function curlResolve(array $target): array
    {
        $entries = [];
        foreach ($target['ips'] as $ip) {
            $entries[] = $target['host'].':'.$target['port'].':'.(str_contains($ip, ':') ? '['.$ip.']' : $ip);
        }

        return $entries;
    }

    /**
     * Resolve a host (or IP literal) to a list of IP addresses.
     *
     * @return array<int, string>
     */
    protected function resolveHost(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }
        $ips = [];
        $records = @dns_get_record($host, DNS_A + DNS_AAAA);
        if (is_array($records)) {
            foreach ($records as $record) {
                if (! empty($record['ip'])) {
                    $ips[] = $record['ip'];
                }
                if (! empty($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }
        if ($ips === []) {
            $resolved = gethostbyname($host);
            if ($resolved !== $host && filter_var($resolved, FILTER_VALIDATE_IP)) {
                $ips[] = $resolved;
            }
        }

        return $ips;
    }

    /**
     * Whether an IP is a publicly routable address (not private/reserved).
     */
    protected function isPublicIp(string $ip): bool
    {
        return (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }
}
