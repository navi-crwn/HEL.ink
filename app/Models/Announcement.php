<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A notice to every HOP user ("short links move to /l/ on …"): the place the
 * terms point to when they say changes are announced on the dashboard first.
 */
class Announcement extends Model
{
    use HasFactory;

    public const LEVELS = ['info', 'warning', 'critical'];

    protected $fillable = ['title', 'body', 'level', 'app_slug', 'published_at', 'expires_at', 'author_id'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** Published and not yet expired, newest first. */
    public function scopeLive(Builder $q): Builder
    {
        return $q->whereNotNull('published_at')->where('published_at', '<=', now())
            ->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderByDesc('published_at');
    }

    public function isLive(): bool
    {
        return $this->published_at && $this->published_at->isPast() && (! $this->expires_at || $this->expires_at->isFuture());
    }
}
