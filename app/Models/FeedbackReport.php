<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * One report from the Feedback form. The text is kept as typed (it is escaped where it is shown). The screenshot is a
 * re-encoded JPEG on the private disk; deleting a report always deletes its file, so every path that removes
 * reports (the daily clean-up, account deletion, Imperium's Delete) goes through delete() on the model, never
 * through a bulk query.
 */
class FeedbackReport extends Model
{
    public const KINDS = ['problem', 'idea', 'other'];

    public const STATUSES = ['new', 'read', 'done'];

    /** What a report can be about: the hub and the six apps. */
    public const APPS = ['hub', 'linkhop', 'biohop', 'pixelhop', 'notehop', 'filehop', 'payhop'];

    /** Days until the IP address is emptied, until a finished report goes, and until any report goes. */
    public const IP_DAYS = 30;

    public const DONE_DAYS = 180;

    public const MAX_DAYS = 365;

    protected $fillable = [
        'kind', 'status', 'app', 'page', 'app_version', 'message', 'user_id', 'username', 'ip_address',
        'user_agent', 'viewport', 'locale', 'screenshot_path', 'read_at', 'done_at',
    ];

    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'done_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::deleted(function (self $report) {
            if ($report->screenshot_path) {
                Storage::disk('local')->delete($report->screenshot_path);
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasScreenshot(): bool
    {
        return $this->screenshot_path !== null && Storage::disk('local')->exists($this->screenshot_path);
    }
}
