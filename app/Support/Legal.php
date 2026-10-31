<?php

namespace App\Support;

use App\Models\LegalAcceptance;
use App\Models\User;
use DateTimeImmutable;
use Exception;
use LogicException;

/**
 * Local, content-bound terms versions for new-account acceptance.
 *
 * The registry ships empty. A published descriptor binds a version to bytes
 * below the configured local artifact directory; no remote retrieval exists.
 */
final class Legal
{
    public const POLICY_TERMS = 'terms';

    public const SOURCE_WEB = 'web';

    public const SOURCE_OAUTH = 'oauth';

    public const SOURCE_APP = 'app';

    public static function requiredVersion(): ?string
    {
        $version = (string) config('legal.terms.required_version', '');

        return $version !== '' ? $version : null;
    }

    /**
     * Resolve and authenticate a published local artifact.
     *
     * @return array{policy:string,version:string,url:string,sha256:string,published_at:string,contents:string}|null
     */
    public static function artifact(string $version, string $policy = self::POLICY_TERMS): ?array
    {
        if ($policy !== self::POLICY_TERMS || ! self::validVersion($version)) {
            return null;
        }
        $published = config('legal.'.$policy.'.published', []);
        if (! is_array($published) || ! array_key_exists($version, $published)) {
            return null;
        }
        $descriptor = $published[$version];
        if (! is_array($descriptor)) {
            throw new LogicException('The published terms descriptor is malformed.');
        }
        $file = $descriptor['file'] ?? null;
        $digest = $descriptor['sha256'] ?? null;
        $publishedAt = $descriptor['published_at'] ?? null;
        if (! is_string($file) || ! self::validFilename($file)
            || ! is_string($digest) || ! preg_match('/\A[a-f0-9]{64}\z/D', $digest)
            || ! is_string($publishedAt) || ! self::validPublicationTime($publishedAt)) {
            throw new LogicException('The published terms descriptor is invalid.');
        }

        $root = config('legal.artifact_directory');
        if (! is_string($root) || $root === '' || ($realRoot = realpath($root)) === false) {
            throw new LogicException('The legal artifact directory is unavailable.');
        }
        $path = realpath($realRoot.DIRECTORY_SEPARATOR.$file);
        if ($path === false || ! is_file($path)
            || ! str_starts_with($path, $realRoot.DIRECTORY_SEPARATOR)) {
            throw new LogicException('The legal artifact path is outside its trusted directory or unavailable.');
        }
        $contents = file_get_contents($path);
        if ($contents === false || ! hash_equals($digest, hash('sha256', $contents))) {
            throw new LogicException('The published terms artifact does not match its registered digest.');
        }

        return [
            'policy' => $policy,
            'version' => $version,
            'url' => route('legal.terms.artifact', ['version' => $version], false),
            'sha256' => $digest,
            'published_at' => $publishedAt,
            'contents' => $contents,
        ];
    }

    public static function isPublished(string $version): bool
    {
        try {
            return self::artifact($version) !== null;
        } catch (LogicException) {
            return false;
        }
    }

    public static function isUsable(?string $version): bool
    {
        return $version === null || self::isPublished($version);
    }

    /** A configured but invalid requirement is an error, never an inactive gate. */
    public static function assertUsable(?string $version): void
    {
        if ($version !== null && self::artifact($version) === null) {
            throw new LogicException('The required terms version is not published with a valid local artifact.');
        }
    }

    /** Public metadata for staged app consumers; no account data is included. */
    public static function consumerMetadata(): array
    {
        $version = self::requiredVersion();
        if ($version === null) {
            return ['active' => false, 'terms' => null];
        }
        $artifact = self::artifact($version);
        if ($artifact === null) {
            throw new LogicException('Cannot discover an unpublished required terms version.');
        }
        unset($artifact['contents']);

        return ['active' => true, 'terms' => $artifact];
    }

    public static function hasAccepted(User $user, string $version, string $policy = self::POLICY_TERMS): bool
    {
        return LegalAcceptance::query()
            ->where('user_id', $user->id)
            ->where('policy', $policy)
            ->where('version', $version)
            ->exists();
    }

    public static function record(User $user, string $source, string $version, string $policy = self::POLICY_TERMS): ?LegalAcceptance
    {
        if ($version === '' || $version !== self::requiredVersion()) {
            return null;
        }
        $artifact = self::artifact($version, $policy);
        if ($artifact === null) {
            return null;
        }

        return LegalAcceptance::record($user, $policy, $version, $source, $artifact['sha256']);
    }

    private static function validVersion(string $version): bool
    {
        return preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,63}\z/D', $version) === 1;
    }

    private static function validFilename(string $file): bool
    {
        return strlen($file) <= 160
            && preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*\.(?:txt|md|html)\z/D', $file) === 1;
    }

    private static function validPublicationTime(string $publishedAt): bool
    {
        if (preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})\z/D', $publishedAt) !== 1) {
            return false;
        }
        try {
            return new DateTimeImmutable($publishedAt) <= now();
        } catch (Exception) {
            return false;
        }
    }
}
