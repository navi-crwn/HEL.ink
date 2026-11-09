<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One sign-in. `kind` separates a real sign-in at the hub (password, Google, an app's own
 * sign-in form) from a single sign-on hand-off into an app, so the hand-offs never push
 * the sign-ins out of the short lists people check for intruders.
 */
class LoginHistory extends Model
{
    public const KIND_LOGIN = 'login';

    public const KIND_SSO = 'sso';

    /** Rows kept per user and kind. */
    public const KEEP = 5;

    protected $fillable = [
        'user_id',
        'kind',
        'ip_address',
        'country',
        'country_name',
        'city',
        'region',
        'provider',
        'isp',
        'device',
        'browser',
        'is_proxy',
        'proxy_type',
        'proxy_confidence',
        'logged_in_at',
    ];

    protected $attributes = [
        'kind' => self::KIND_LOGIN,
    ];

    protected $casts = [
        'logged_in_at' => 'datetime',
        'is_proxy' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Real sign-ins at the hub. */
    public function scopeSignIns(Builder $q): Builder
    {
        return $q->where('kind', self::KIND_LOGIN);
    }

    /** Single sign-on hand-offs into the apps. */
    public function scopeHandoffs(Builder $q): Builder
    {
        return $q->where('kind', self::KIND_SSO);
    }

    /** Keep only a user's latest few rows of each kind. */
    public static function keepLatest(int $userId, int $keep = self::KEEP): void
    {
        foreach ([self::KIND_LOGIN, self::KIND_SSO] as $kind) {
            $old = static::where('user_id', $userId)->where('kind', $kind)
                ->orderBy('logged_in_at', 'desc')->orderBy('id', 'desc')->skip($keep)->take(1000)->pluck('id');
            if ($old->isNotEmpty()) {
                static::whereIn('id', $old)->delete();
            }
        }
    }
}
