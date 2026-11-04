<?php

namespace Hop\Safety;

/**
 * Knows what an encrypted NoteHop link looks like, so an app that stores destinations never stores one.
 *
 * NoteHop keeps the note key in the address fragment: https://n.hel.ink/TOKEN#k=KEY, and the owner link adds
 * &a=ADMIN. Browsers never send a fragment to a server, which is why NoteHop cannot read the note. An app that
 * stores the whole destination (a shortener, a bio page, a report form) and redirects to it holds the key in its
 * database, its logs and every outside lookup it makes. A destination is therefore refused when its host is a
 * NoteHop host and its fragment has a k or an a parameter with a value (the two names NoteHop's note.js reads from
 * location.hash). Other hosts and other fragments are ordinary destinations.
 *
 * The address is read the way a browser reads it (WHATWG URL): tabs and line breaks dropped, leading control
 * characters trimmed, any number of slashes or backslashes after "http:", a backslash ends the host like a slash,
 * the last @ ends the user info, percent escapes decoded, then UTS 46 mapped, so full-width letters, U+3002, soft
 * hyphens and a trailing dot all land on the host the browser connects to. That mapping needs ext-intl, which
 * composer.json requires; without it a host that is not plain ASCII cannot be read, and an unreadable host with a
 * secret in the fragment is refused (never guessed to be harmless).
 *
 * Host apps add their own hosts (a registered NoteHop app, a self-hosted second domain) with
 * EncryptedNoteLink::resolveHostsUsing(), or through config('safety.note_hosts').
 */
class EncryptedNoteLink
{
    /** The fragment parameters NoteHop treats as secrets: k is the note key, a the owner (admin) token. */
    public const SECRET_PARAMS = ['k', 'a'];

    /** Hosts used when nothing is configured. */
    public const DEFAULT_HOSTS = ['note.hel.ink', 'n.hel.ink'];

    /** The English text is also the key in each app's lang/*.json. */
    public const MESSAGE = 'This link opens an encrypted NoteHop note, and the part after the # is its key. Shortening it would store that key on this server. Share the NoteHop link as it is (its share button already gives a short address), or use a Basic note, which the server can read anyway.';

    /** An Android intent address carries a fallback address (percent-encoded) that the browser opens when the app is missing. */
    private const INTENT_FALLBACK = '~^intent://[^#]*#Intent;(?:[^;]*;)*?S\.browser_fallback_url=([^;]*)~i';

    /** What ends an address inside a longer text. */
    private const TOKEN = '[^\s"\'<>()\[\]{}]';

    /** @var list<callable(): iterable<string|null>> */
    private static array $hostSources = [];

    /** @var list<string>|null */
    private ?array $hosts = null;

    /** @param  list<string>|null  $hosts  Fixed host list (tests, other callers); null reads config and the registered sources. */
    public function __construct(?array $hosts = null)
    {
        if ($hosts !== null) {
            $this->hosts = array_values(array_unique(array_filter(array_map(self::hostOf(...), $hosts))));
        }
    }

    /** Let the host app contribute hosts (for example the NoteHop row of its app registry). Pass null to clear. */
    public static function resolveHostsUsing(?callable $source): void
    {
        if ($source === null) {
            self::$hostSources = [];

            return;
        }
        self::$hostSources[] = $source;
    }

    /** @return list<string> Lower-case NoteHop hosts. */
    public function hosts(): array
    {
        if ($this->hosts !== null) {
            return $this->hosts;
        }
        try {
            $hosts = (array) config('safety.note_hosts', self::DEFAULT_HOSTS);
        } catch (\Throwable) {
            $hosts = self::DEFAULT_HOSTS; // no application booted (a plain unit test, a script)
        }
        foreach (self::$hostSources as $source) {
            try {
                foreach ($source() as $h) {
                    $hosts[] = $h;
                }
            } catch (\Throwable) {
                // a source that cannot answer yet (no database during install) must not break validation
            }
        }

        return array_values(array_unique(array_filter(array_map(self::hostOf(...), $hosts))));
    }

    /** True for a NoteHop address whose fragment carries the note key or the owner token. */
    public function matches(?string $url): bool
    {
        $clean = self::browserClean((string) $url);
        // "intent://x#Intent;...;S.browser_fallback_url=https%3A%2F%2Fnote.hel.ink%2FID%23k%3DKEY;end" opens the note in a browser
        if (preg_match(self::INTENT_FALLBACK, $clean, $intent)) {
            return $this->matches(rawurldecode($intent[1]));
        }
        if ($clean === '' || ! str_contains($clean, '#')) {
            return false;
        }
        // No scheme (typed or pasted bare, with or without a port): the browser's address bar, and the apps that
        // put https:// in front, read it as https.
        if (! preg_match('~^[a-z][a-z0-9+.\-]*:~i', $clean) || preg_match('~^[^/:]+:\d+(/|$)~', $clean)) {
            $clean = 'https://'.ltrim($clean, '/\\');
        }
        // The forms put https:// in front of anything that does not start with "http://", so "https:/note.hel.ink/..."
        // can arrive as "https://https:/note.hel.ink/..." (the host is then "https", but the key is stored all the same).
        while (preg_match('~^https?:[/\\\\]*[\x00-\x20]*(?=https?:)~i', $clean)) {
            $clean = preg_replace('~^https?:[/\\\\]*[\x00-\x20]*~i', '', $clean);
        }
        // After "http:" or "https:" a browser skips every slash and backslash ("https:/host", "https:host" and
        // "https:////host" all name the host); the host ends at the next slash, backslash, ? or #.
        if (! preg_match('~^https?:[/\\\\]*([^/\\\\?#]*)~i', $clean, $m)) {
            return false;
        }
        $authority = substr(strrchr('@'.$m[1], '@'), 1);
        $host = self::normalizeHost((string) preg_replace('/:\d*$/', '', $authority));
        if (! self::fragmentHasSecret(substr($clean, (int) strpos($clean, '#') + 1))) {
            return false;
        }

        // null: a host that cannot be read without ext-intl. It might be a NoteHop host, so it counts as one.
        return $host === null || in_array($host, $this->hosts(), true);
    }

    /**
     * True when free text (a bio, a report, a title) contains an address of that kind, with or without a scheme.
     * Only text that has a "#k=" or "#a=" part is looked at.
     */
    public function containsKey(?string $text): bool
    {
        // Up to four rounds of percent-decoding: an address inside another one's query or an intent:// fallback
        // ("...browser_fallback_url=https%3A%2F%2Fnote.hel.ink%2FID%23k%3DKEY") is still an address.
        $text = (string) $text;
        for ($round = 0; $round < 4; $round++) {
            if (str_contains($text, '#') || str_contains(strtolower($text), '%23')) {
                foreach (self::addressesIn($text) as $address) {
                    if ($this->matches($address)) {
                        return true;
                    }
                }
            }
            $decoded = rawurldecode($text);
            if ($decoded === $text) {
                break;
            }
            $text = $decoded;
        }

        return false;
    }

    /** The error text for the person who tried to save the link, in the current language. */
    public function message(): string
    {
        return function_exists('__') ? __(self::MESSAGE) : self::MESSAGE;
    }

    /**
     * Read the fragment the way NoteHop does (new URLSearchParams(location.hash.slice(1))): tabs and line breaks
     * dropped, one leading "?" dropped ("#?k=..." is the k parameter), pairs split on &, names and values
     * percent-decoded, so "%6B=..." is the k parameter too. A parameter with an empty value carries nothing.
     */
    public static function fragmentHasSecret(string $fragment): bool
    {
        $fragment = preg_replace('/[\t\r\n]/', '', ltrim($fragment, '#')) ?? '';
        if (str_starts_with($fragment, '?')) {
            $fragment = substr($fragment, 1);
        }
        foreach (explode('&', $fragment) as $pair) {
            [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
            if (in_array(urldecode($name), self::SECRET_PARAMS, true) && urldecode($value) !== '') {
                return true;
            }
        }

        return false;
    }

    /** The address without its fragment: what a server may log, send to a checker or show in an email. */
    public static function withoutFragment(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }
        $at = strpos($url, '#');

        return $at === false ? $url : substr($url, 0, $at);
    }

    /**
     * The address with only the secret parameters (k, a) taken out of its fragment; any other part of the fragment
     * stays ("#new=1&k=KEY" becomes "#new=1", "#k=KEY" becomes nothing). A leading "?" in the fragment is kept.
     * The fallback address of an Android intent is cleaned the same way.
     */
    public static function withoutSecrets(string $url): string
    {
        if (preg_match(self::INTENT_FALLBACK, $url, $intent, PREG_OFFSET_CAPTURE)) {
            [$value, $offset] = $intent[1];

            return substr($url, 0, $offset).rawurlencode(self::withoutSecrets(rawurldecode($value))).substr($url, $offset + strlen($value));
        }
        $at = strpos($url, '#');
        if ($at === false) {
            return $url;
        }
        $fragment = substr($url, $at + 1);
        $mark = str_starts_with($fragment, '?') ? '?' : '';
        $kept = array_filter(
            explode('&', $mark === '' ? $fragment : substr($fragment, 1)),
            fn (string $pair) => $pair !== ''
                && ! in_array(urldecode(explode('=', preg_replace('/[\t\r\n]/', '', $pair), 2)[0]), self::SECRET_PARAMS, true),
        );

        return substr($url, 0, $at).($kept === [] ? '' : '#'.$mark.implode('&', $kept));
    }

    /**
     * Take the secret parameters out of every NoteHop address in a text (a report, a log line). Only an address
     * on a NoteHop host is touched, so "https://example.com/#a=1" stays as it was, and so does every other part
     * of the text. An address whose structure is hidden by percent-encoding (an address inside another one's
     * query) loses its whole fragment. Used by the scrub commands and on stored text.
     */
    public function scrub(string $text): string
    {
        return preg_replace_callback(
            '~'.self::TOKEN.'+~',
            function (array $m): string {
                $token = $m[0];
                if (! str_contains($token, '#') && ! str_contains(strtolower($token), '%23')) {
                    return $token;
                }
                $plain = preg_replace('/%23/i', '#', $token, 1);
                if ($this->matches($plain)) {
                    return self::withoutSecrets($plain);
                }

                return $this->containsKey($token) ? (preg_split('/#|%23/i', $token, 2)[0]) : $token;
            },
            $text,
        ) ?? $text;
    }

    /**
     * Like scrub(), for a column that may hold JSON (Laravel casts store it with escaped slashes and \\uXXXX escapes,
     * so a full-width host letter is not visible as such in the raw text). A value that decodes to an array is
     * scrubbed string by string and written back the way Laravel writes it; anything else goes through scrub().
     */
    public function scrubJson(string $value): string
    {
        $data = json_decode($value, true);
        if (! is_array($data)) {
            return $this->scrub($value);
        }
        array_walk_recursive($data, function (&$v) {
            if (is_string($v)) {
                $v = $this->scrub($v);
            }
        });

        return json_encode($data) ?: $this->scrub($value);
    }

    /**
     * The host as a browser reads it: percent-decoded, UTS 46 mapped, lower case, without a trailing dot or a
     * leading www. Null when that needs ext-intl and it is missing (the host is not plain ASCII).
     */
    public static function normalizeHost(string $host): ?string
    {
        $host = rawurldecode(trim($host, '[]'));
        if ($host === '') {
            return ''; // idn_to_ascii() throws on an empty string
        }
        if (static::intlAvailable()) {
            $ascii = idn_to_ascii($host, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);
            if ($ascii !== false) {
                $host = $ascii;
            }
        } elseif (preg_match('/[^\x20-\x7e]/', $host)) {
            return null;
        }

        return preg_replace('/^www\./', '', rtrim(strtolower($host), '.'));
    }

    protected static function intlAvailable(): bool
    {
        return function_exists('idn_to_ascii');
    }

    /** Drop what a browser ignores: leading and trailing control characters and spaces, tabs and line breaks anywhere. */
    private static function browserClean(string $url): string
    {
        return preg_replace('/^[\x00-\x20]+|[\x00-\x20]+$/', '', preg_replace('/[\t\r\n]/', '', $url) ?? '') ?? '';
    }

    /** @return list<string> */
    private static function addressesIn(string $text): array
    {
        // Every run without whitespace or a quote that has a # in it is a candidate address.
        // No /u: text that is not valid UTF-8 makes a /u pattern fail, and a failed match finds nothing.
        $text = preg_replace('/%23/i', '#', $text) ?? $text;
        if (preg_match_all('~'.self::TOKEN.'+~', $text, $m) === false) {
            return [];
        }
        $found = [];
        foreach ($m[0] as $token) {
            if (! str_contains($token, '#')) {
                continue;
            }
            $found[] = $token;
            // an address that starts inside the run (intent://...;S.browser_fallback_url=https://note...#k=...)
            if (preg_match_all('~(?:https?:)?//(?=[^/])~i', $token, $starts, PREG_OFFSET_CAPTURE)) {
                foreach ($starts[0] as [, $at]) {
                    if ($at > 0) {
                        $found[] = substr($token, $at);
                    }
                }
            }
        }

        return $found;
    }

    private static function hostOf(mixed $url): ?string
    {
        if (! is_string($url) || trim($url) === '') {
            return null;
        }
        $url = trim($url);
        $host = parse_url(str_contains($url, '://') ? $url : 'https://'.$url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? self::normalizeHost($host) : null;
    }
}
