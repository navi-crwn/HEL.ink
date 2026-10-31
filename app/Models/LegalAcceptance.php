<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $user_id
 * @property string $policy
 * @property string $version
 * @property string $sha256
 * @property string $source
 * @property Carbon $accepted_at
 */
class LegalAcceptance extends Model
{
    protected $fillable = ['user_id', 'policy', 'version', 'sha256', 'source', 'accepted_at'];

    protected function casts(): array
    {
        return ['accepted_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Never overwrite an existing acceptance or its content digest. */
    public static function record(User $user, string $policy, string $version, string $source, string $sha256): self
    {
        if (preg_match('/\A[a-f0-9]{64}\z/D', $sha256) !== 1) {
            throw new LogicException('A legal acceptance requires a lowercase SHA-256 digest.');
        }
        $acceptance = static::query()->firstOrCreate(
            ['user_id' => $user->id, 'policy' => $policy, 'version' => $version],
            ['sha256' => $sha256, 'source' => $source, 'accepted_at' => now()],
        );
        if (! hash_equals($acceptance->sha256, $sha256)) {
            throw new LogicException('The existing acceptance has a different artifact digest.');
        }

        return $acceptance;
    }
}
