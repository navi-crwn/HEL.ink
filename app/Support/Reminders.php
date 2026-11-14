<?php

namespace App\Support;

use App\Models\Announcement;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * HOP ID's reminder cards on the dashboard: the account checklist, the invitation to turn
 * on two-factor, and the warning that the recovery codes are running out. A person can hide
 * a card; it stays away for seven days and then shows again while its task is still open.
 * A card whose task is done is never shown, hidden or not. Functional banners (verify your
 * email) are not reminders and cannot be hidden. An announcement card can be hidden the same way (key `ann-<id>`,
 * see announcementKey) unless it is critical.
 */
final class Reminders
{
    public const HIDE_DAYS = 7;

    public const ACCOUNT_HEALTH = 'account-health';

    public const TWO_FACTOR = 'two-factor';

    public const RECOVERY_CODES = 'recovery-codes';

    /** @var list<string> */
    public const KEYS = [self::ACCOUNT_HEALTH, self::TWO_FACTOR, self::RECOVERY_CODES];

    /** Warn when this many recovery codes, or fewer, are left. */
    public const LOW_CODES = 2;

    private const TABLE = 'hub_reminder_dismissals';

    /** The key of the card of one announcement: `ann-<id>`, the same in every HOP app. */
    public static function announcementKey(Announcement $announcement): string
    {
        return 'ann-'.$announcement->getKey();
    }

    /** A card the person may hide: one of the fixed reminders, or `ann-<digits>` (the announcement itself is checked by the caller). */
    public static function isKey(string $key): bool
    {
        return in_array($key, self::KEYS, true) || preg_match('/^ann-[0-9]{1,18}$/', $key) === 1;
    }

    public static function dismiss(User $user, string $key, ?CarbonInterface $at = null): void
    {
        $at ??= now();
        DB::table(self::TABLE)->updateOrInsert(
            ['user_id' => $user->id, 'key' => $key],
            ['dismissed_at' => $at, 'updated_at' => $at, 'created_at' => $at],
        );
    }

    /**
     * The reminders the person hid less than seven days ago.
     *
     * @return list<string>
     */
    public static function hidden(User $user, ?CarbonInterface $now = null): array
    {
        $now ??= now();

        return DB::table(self::TABLE)
            ->where('user_id', $user->id)
            ->where('dismissed_at', '>', $now->copy()->subDays(self::HIDE_DAYS))
            ->pluck('key')->map(fn ($key) => (string) $key)->all();
    }

    /**
     * The reminders to draw now: those whose task is open and that are not hidden.
     *
     * @param  array{done: int, total: int}|null  $health  the account checklist (null: the account has none)
     * @return list<string>
     */
    public static function due(User $user, HubDashboard $dashboard, ?array $health, ?CarbonInterface $now = null): array
    {
        $hidden = self::hidden($user, $now);
        $open = [];
        $checklist = $health !== null && $health['done'] < $health['total'];
        if ($checklist) {
            $open[] = self::ACCOUNT_HEALTH;
        }
        $checklistShown = $checklist && ! in_array(self::ACCOUNT_HEALTH, $hidden, true);
        // The checklist already asks for two-factor; the separate invitation is for when the checklist is not on screen.
        if ($health !== null && ! $checklistShown && ! $user->hasTwoFactor() && $dashboard->hasOwnPassword()) {
            $open[] = self::TWO_FACTOR;
        }
        if ($user->hasTwoFactor() && $dashboard->recoveryCodesLeft() <= self::LOW_CODES) {
            $open[] = self::RECOVERY_CODES;
        }

        return array_values(array_diff($open, $hidden));
    }

    /**
     * For the account export: what was hidden and when.
     *
     * @return list<array{key: string, dismissed_at: string}>
     */
    public static function export(User $user): array
    {
        return DB::table(self::TABLE)->where('user_id', $user->id)->orderBy('key')->get(['key', 'dismissed_at'])
            ->map(fn ($row) => ['key' => (string) $row->key, 'dismissed_at' => (string) $row->dismissed_at])->all();
    }
}
