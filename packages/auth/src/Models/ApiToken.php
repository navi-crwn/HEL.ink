<?php

namespace Hop\Auth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * The shared api_tokens table: made and revoked at the hub, accepted by every
 * app. `scopes` limits what a token may do (null: everything).
 */
class ApiToken extends Model
{
    /** Scope => what it allows. Apps check with can('link:create') etc. */
    public const SCOPES = [
        'link:read' => 'Read short links and their stats',
        'link:create' => 'Create short links',
        'link:write' => 'Edit and delete short links',
        'bio:read' => 'Read bio pages',
        'bio:write' => 'Create, edit and delete bio pages',
        'pixel:read' => 'Read images and albums',
        'pixel:write' => 'Upload and delete images',
    ];

    protected $table = 'api_tokens';

    protected static ?string $userModel = null;

    protected $fillable = ['user_id', 'name', 'token', 'rate_limit', 'scopes', 'last_used_at', 'expires_at'];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return ['scopes' => 'array', 'last_used_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    /** @return array{model: static, plain_token: string} */
    public static function generate(int $userId, string $name, int $rateLimit = 100, ?\DateTimeInterface $expiresAt = null, ?array $scopes = null): array
    {
        $plainToken = 'hlk_'.Str::random(40);
        $model = static::create([
            'user_id' => $userId,
            'name' => $name,
            'token' => hash('sha256', $plainToken),
            'rate_limit' => $rateLimit,
            'scopes' => $scopes,
            'expires_at' => $expiresAt,
        ]);

        return ['model' => $model, 'plain_token' => $plainToken];
    }

    public static function findByPlainToken(string $plainToken): ?static
    {
        return static::where('token', hash('sha256', $plainToken))->first();
    }

    public function isValid(): bool
    {
        return ! ($this->expires_at && $this->expires_at->isPast());
    }

    /** Whether the token covers a scope: an exact match, a "prefix:*" wildcard, or no scopes at all. */
    public function can(string $scope): bool
    {
        $scopes = $this->scopes;
        if ($scopes === null || $scopes === []) {
            return true;
        }
        [$prefix] = explode(':', $scope, 2) + [1 => ''];

        return in_array($scope, $scopes, true) || in_array($prefix.':*', $scopes, true) || in_array('*', $scopes, true);
    }

    public function markAsUsed(): void
    {
        $this->forceFill(['last_used_at' => now()])->saveQuietly();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(static::$userModel ?? config('hop-auth.user_model', 'App\\Models\\User'));
    }
}
