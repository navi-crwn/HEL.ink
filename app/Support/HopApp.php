<?php

namespace App\Support;

/**
 * One app of the HOP family: from config/hop.php (LinkHop, BioHop, PixelHop) or
 * from the HOP ID registry table `apps` (Imperium, NoteHop, …). The hub issues
 * SSO tickets for it, lists it in the launcher unless hidden, and watches it.
 */
final class HopApp implements \JsonSerializable
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
        public readonly bool $hidden = false,
        public readonly array $capabilities = [],
    ) {}

    /**
     * The public face of the app. The SSO secret and the internal endpoints never
     * leave the hub, whatever serializes this object (JSON responses, @js, logs).
     */
    public function jsonSerialize(): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'tagline' => $this->tagline,
            'url' => $this->url,
            'icon' => $this->icon,
            'color' => $this->color,
            'publicPrefix' => $this->publicPrefix,
            'repo' => $this->repo,
        ];
    }

    public function __debugInfo(): array
    {
        return $this->jsonSerialize() + ['secret' => $this->secret === null ? null : '[hidden]'];
    }

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
            secret: ! empty($c['registry']) ? ($c['secret'] ?? null) : (($c['secret'] ?? null) ?: (config('hop.sso_secret') ?: null)),
            icon: (string) ($c['icon'] ?? 'globe'),
            color: (string) ($c['color'] ?? 'indigo'),
            publicPrefix: $c['public_prefix'] ?? null,
            repo: $c['repo'] ?? null,
        );
    }

    /** An app from the registry table; its own secret, no logout or export endpoint known. */
    public static function fromRegistry(\App\Models\HopApp $m, array $display = []): self
    {
        $slug = (string) $m->slug;
        if ($display !== []) {
            // Described in config/hop.php for people (name, tagline, icon, health); SSO stays the registry's.
            return new self(
                slug: $slug,
                name: (string) ($display['name'] ?? $m->name),
                tagline: (string) ($display['tagline'] ?? ''),
                url: rtrim((string) $m->url, '/'),
                callback: (string) ($m->callback_path ?: '/auth/sso/callback'),
                start: $m->start_path ?: '/auth/sso',
                logout: $display['logout'] ?? null,
                export: $display['export'] ?? null,
                health: (string) ($display['health'] ?? '/healthz'),
                check: $display['check'] ?? null,
                secret: $m->secrets()[0] ?? null,
                icon: (string) ($display['icon'] ?? $m->icon ?? 'globe'),
                color: (string) ($display['color'] ?? $m->color ?? 'indigo'),
                publicPrefix: null,
                repo: $display['repo'] ?? null,
                hidden: false,
                capabilities: (array) ($m->capabilities ?? []),
            );
        }

        return new self(
            slug: $slug,
            name: (string) $m->name,
            tagline: (string) config('hop.registry_taglines.'.$slug, ''),
            url: rtrim((string) $m->url, '/'),
            callback: (string) ($m->callback_path ?: '/auth/sso/callback'),
            start: $m->start_path ?: '/auth/sso',
            logout: config('hop.registry_logout.'.$slug),
            export: null,
            // The registry apps are Go services with /healthz (Laravel apps are in config).
            health: (string) config('hop.registry_health.'.$slug, '/healthz'),
            check: null,
            secret: $m->secrets()[0] ?? null,
            icon: (string) ($m->icon ?: 'globe'),
            color: (string) ($m->color ?: 'indigo'),
            publicPrefix: null,
            repo: config('hop.registry_repos.'.$slug),
            hidden: (bool) $m->getAttribute('hidden'),
            capabilities: (array) ($m->capabilities ?? []),
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
