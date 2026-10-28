<?php

namespace App\Support;

/**
 * One app of the HOP family, as described in config/hop.php. The hub issues
 * SSO tickets for it, lists it in the launcher and watches its /up endpoint.
 */
final class HopApp
{
    public function __construct(
        public readonly string $slug,
        public readonly string $name,
        public readonly string $tagline,
        public readonly string $url,
        public readonly string $callback,
        public readonly ?string $start,
        public readonly ?string $logout,
        public readonly ?string $export,
        public readonly string $health,
        public readonly ?string $check,
        public readonly ?string $secret,
        public readonly string $icon,
        public readonly string $color,
        public readonly ?string $publicPrefix,
        public readonly ?string $repo,
    ) {}

    public static function fromConfig(string $slug, array $c): self
    {
        return new self(
            slug: $slug,
            name: (string) ($c['name'] ?? ucfirst($slug)),
            tagline: (string) ($c['tagline'] ?? ''),
            url: rtrim((string) ($c['url'] ?? ''), '/'),
            callback: (string) ($c['callback'] ?? '/auth/sso/callback'),
            start: $c['start'] ?? '/auth/sso',
            logout: $c['logout'] ?? null,
            export: $c['export'] ?? null,
            health: (string) ($c['health'] ?? '/up'),
            check: $c['check'] ?? null,
            secret: $c['secret'] ?: (config('hop.sso_secret') ?: null),
            icon: (string) ($c['icon'] ?? 'globe'),
            color: (string) ($c['color'] ?? 'indigo'),
            publicPrefix: $c['public_prefix'] ?? null,
            repo: $c['repo'] ?? null,
        );
    }

    /** Configured for SSO: an https/http URL and a secret of 32+ characters. */
    public function isConfigured(): bool
    {
        return strlen((string) $this->secret) >= 32 && preg_match('#^https?://#', $this->url) === 1;
    }

    public function callbackUrl(): string
    {
        return $this->url.$this->callback;
    }

    /** Where "Open" sends people: the app's SSO start page, which bounces to the hub and back. */
    public function startUrl(?string $next = null): string
    {
        $url = $this->url.($this->start ?: '/auth/sso');

        return $next ? $url.'?next='.rawurlencode($next) : $url;
    }

    public function logoutUrl(): ?string
    {
        return $this->logout ? $this->url.$this->logout : null;
    }

    public function healthUrl(): string
    {
        return $this->url.$this->health;
    }

    public function exportUrl(): ?string
    {
        return $this->export ? $this->url.$this->export : null;
    }

    /** Origin (scheme://host[:port]) for the CSP form-action list. */
    public function origin(): ?string
    {
        $p = parse_url($this->url);
        if (! $p || ! isset($p['scheme'], $p['host'])) {
            return null;
        }

        return $p['scheme'].'://'.$p['host'].(isset($p['port']) ? ':'.$p['port'] : '');
    }
}
