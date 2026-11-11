<?php

namespace App\Support;

use Propaganistas\LaravelDisposableEmail\Facades\DisposableDomains;

/**
 * Which e-mail domains cannot be used for an account. The refused set is the union of the committed snapshot of the
 * disposable-email-domains list, its refresh on the server (storage), the list of laravel-disposable-email and the owner's
 * own `hop.email.block`; `hop.email.allow` wins over all of them. Everything is read from local files: nothing here
 * touches the network. A domain also covers its subdomains, and internationalised domains are compared in ASCII.
 */
final class EmailDomains
{
    public const SNAPSHOT = 'resources/data/disposable_email_blocklist.conf';

    /** @var array<string, true>|null */
    private static ?array $listed = null;

    public static function snapshotPath(): string
    {
        return base_path(self::SNAPSHOT);
    }

    /** The copy the weekly refresh keeps on the server; the snapshot is used when it is missing or unreadable. */
    public static function refreshedPath(): string
    {
        return storage_path('framework/disposable_email_blocklist.conf');
    }

    public static function forget(): void
    {
        self::$listed = null;
    }

    /** Lower case, no dots at the ends, ASCII form of an internationalised domain. */
    public static function normalise(string $domain): string
    {
        $domain = trim(mb_strtolower(trim($domain)), '.');
        if ($domain !== '' && preg_match('/[^\x20-\x7e]/', $domain) === 1 && function_exists('idn_to_ascii')) {
            $ascii = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            if (is_string($ascii) && $ascii !== '') {
                return $ascii;
            }
        }

        return $domain;
    }

    public static function isDomain(string $domain): bool
    {
        return strlen($domain) <= 253 && preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $domain) === 1;
    }

    /**
     * The domains of a list in the `.conf` format (one per line; blank lines and `#` comments are ignored).
     *
     * @return list<string>
     */
    public static function parse(string $text): array
    {
        $out = [];
        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $domain = self::normalise($line);
            if (self::isDomain($domain)) {
                $out[$domain] = true;
            }
        }

        return array_keys($out);
    }

    /** @return array<string, true> */
    private static function listed(): array
    {
        if (self::$listed === null) {
            $set = [];
            foreach ([self::snapshotPath(), self::refreshedPath()] as $file) {
                if (is_file($file) && ($text = @file_get_contents($file)) !== false) {
                    foreach (self::parse($text) as $d) {
                        $set[$d] = true;
                    }
                }
            }
            self::$listed = $set;
        }

        return self::$listed;
    }

    /** @return list<string> the domain of the address and each parent domain, longest first (at least two labels) */
    public static function candidates(string $email): array
    {
        $domain = self::normalise((string) substr(strrchr('@'.$email, '@'), 1));
        if ($domain === '') {
            return [];
        }
        $labels = explode('.', $domain);
        $out = [];
        for ($i = 0, $n = count($labels); $i < max(1, $n - 1); $i++) {
            $out[] = implode('.', array_slice($labels, $i));
        }

        return $out;
    }

    /** @param  list<string>  $domains */
    private static function setOf(array $domains): array
    {
        $set = [];
        foreach ($domains as $d) {
            if (($d = self::normalise((string) $d)) !== '') {
                $set[$d] = true;
            }
        }

        return $set;
    }

    /** True when an account may not be given this address. */
    public static function blocked(string $email): bool
    {
        $candidates = self::candidates($email);
        if ($candidates === []) {
            return false;
        }
        $allow = self::setOf((array) config('hop.email.allow', []));
        foreach ($candidates as $c) {
            if (isset($allow[$c])) {
                return false;
            }
        }
        $listed = self::listed() + self::setOf((array) config('hop.email.block', []));
        foreach ($candidates as $c) {
            if (isset($listed[$c])) {
                return true;
            }
            try {
                if (DisposableDomains::isDisposable('x@'.$c)) {
                    return true;
                }
            } catch (\Throwable) {
                // The package list is a second source; its failure never blocks or lets anyone through on its own.
            }
        }

        return false;
    }
}
