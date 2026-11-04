<?php

namespace Hop\Auth;

/**
 * What an app does when HOP ID tells it something happened to an account
 * (POST /hop/events, HOP App Protocol §6). The hub sends: `session.revoked`,
 * `account.status` (data.status), `account.deleted` and `profile.updated`;
 * every one carries data.sub, the account id in the shared users table.
 *
 * Built in: `session.revoked` and `account.deleted` end every session of that
 * account here (SessionRevoker). The app adds its own with HopEvents::on() in
 * a service provider (a handler gets the data array and the whole event), and
 * can drop a built-in one with HopEvents::forget(). `account.status` has no
 * built-in handler: what a suspension means is the app's call (LinkHop and
 * BioHop already sign a suspended account out on its next request).
 */
final class HopEvents
{
    /** @var array<string, list<callable(array<string, mixed>, array<string, mixed>): mixed>> */
    private static array $handlers = [];

    private static bool $booted = false;

    /** @param callable(array<string, mixed> $data, array<string, mixed> $event): mixed $handler */
    public static function on(string $type, callable $handler): void
    {
        self::boot();
        self::$handlers[$type][] = $handler;
    }

    /** Drops every handler for a type, the built-in one included. */
    public static function forget(string $type): void
    {
        self::boot();
        unset(self::$handlers[$type]);
    }

    /** Back to the built-in handlers (for tests). */
    public static function reset(): void
    {
        self::$handlers = [];
        self::$booted = false;
    }

    /**
     * Runs the handlers for an event. True when at least one of them acted: a handler says "not for me"
     * (an event about no account it can find) by returning false. A handler that throws lets the
     * exception out so the caller can ask the hub to retry.
     *
     * @param  array<string, mixed>  $event  the decoded envelope: id, type, at, data
     */
    public static function dispatch(array $event): bool
    {
        self::boot();
        $type = (string) ($event['type'] ?? '');
        $data = is_array($event['data'] ?? null) ? $event['data'] : [];
        $acted = false;
        foreach (self::$handlers[$type] ?? [] as $handler) {
            $acted = $handler($data, $event) !== false || $acted;
        }

        return $acted;
    }

    /** The account an event is about, as an id in the shared users table; null when data.sub is not one. */
    public static function accountId(array $data): ?int
    {
        $sub = $data['sub'] ?? $data['hop_id'] ?? null;

        return (is_string($sub) || is_int($sub)) && ctype_digit((string) $sub) && (string) $sub !== '0' ? (int) $sub : null;
    }

    private static function boot(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;
        $endSessions = function (array $data): bool {
            $id = self::accountId($data);
            if ($id !== null) {
                SessionRevoker::revoke($id);
            }

            return $id !== null;
        };
        self::$handlers = ['session.revoked' => [$endSessions], 'account.deleted' => [$endSessions]];
    }
}
