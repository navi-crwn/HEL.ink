<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\LoginHistory;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * What the signed-in person sees on the hub dashboard, computed from their own
 * rows only: the greeting, the account-health checklist, the last sign-ins and
 * the last activity. Every query is scoped to the user and limited.
 */
final class HubDashboard
{
    public const LOGINS = 3;

    public const ACTIVITY = 5;

    private ?bool $ownPassword = null;

    public function __construct(private readonly User $user, private readonly ?CarbonInterface $now = null) {}

    /** "morning", "afternoon" or "evening" in the person's timezone (the app's when none is set). */
    public function period(): string
    {
        $now = $this->now ?? now();
        try {
            $now = $now->copy()->setTimezone($this->user->timezone ?: config('app.timezone'));
        } catch (\Throwable) {
            // An unknown timezone name: keep the server time.
        }
        $hour = (int) $now->format('G');

        return $hour < 11 ? 'morning' : ($hour < 18 ? 'afternoon' : 'evening');
    }

    public function firstName(): string
    {
        $name = trim((string) $this->user->name);

        return Str::of($name !== '' ? $name : (string) ($this->user->username ?: $this->user->email))->before(' ')->toString();
    }

    /**
     * One line about the apps, from the watchdog summary.
     *
     * @param  array<string, array{app: HopApp, ok: ?bool}>  $summary
     * @return array{state: string, up: int, total: int, down: list<string>}
     */
    public static function status(array $summary): array
    {
        $up = 0;
        $down = [];
        foreach ($summary as $row) {
            if ($row['ok'] === true) {
                $up++;
            } elseif ($row['ok'] === false) {
                $down[] = $row['app']->name;
            }
        }
        $state = $down !== [] ? 'problem' : ($up > 0 ? 'ok' : 'unknown');

        return ['state' => $state, 'up' => $up, 'total' => count($summary), 'down' => $down];
    }

    /**
     * Does the account have a password the person set? Google sign-up stores a random
     * placeholder, so for Google accounts the only trace is the activity log row written
     * when a password is created or changed.
     */
    public function hasOwnPassword(): bool
    {
        if ($this->ownPassword !== null) {
            return $this->ownPassword;
        }
        if (empty($this->user->password)) {
            return $this->ownPassword = false;
        }
        if (! $this->user->google_id) {
            return $this->ownPassword = true;
        }

        return $this->ownPassword = ActivityLog::where('user_id', $this->user->id)->where('model', 'Password')->exists();
    }

    /** Recovery codes still unused (0 when two-factor is off or the codes cannot be read). */
    public function recoveryCodesLeft(): int
    {
        if (! $this->user->hasTwoFactor() || ! $this->user->two_factor_recovery_codes) {
            return 0;
        }
        try {
            $codes = json_decode(decrypt($this->user->two_factor_recovery_codes), true);
        } catch (\Throwable) {
            return 0;
        }

        return is_array($codes) ? count($codes) : 0;
    }

    /**
     * The account-health checklist. Steps whose state the data cannot tell are not listed
     * (there is no "export your data" step: an export leaves no trace).
     *
     * @return array{steps: list<array{key: string, label: string, hint: string, done: bool, url: string}>, done: int, total: int, percent: int}
     */
    public function health(): array
    {
        $user = $this->user;
        $password = $this->hasOwnPassword();
        $twoFactor = $user->hasTwoFactor();
        $codes = $this->recoveryCodesLeft();
        $settings = route('settings');
        $steps = [
            [
                'key' => 'email',
                'label' => 'Verify your email',
                'hint' => 'Needed to open the apps',
                'done' => $user->hasVerifiedEmail(),
                'url' => route('verification.notice'),
            ],
            [
                'key' => 'password',
                'label' => 'Set a password',
                'hint' => 'So you can also sign in with your email',
                'done' => $password,
                'url' => $settings.'#account',
            ],
            [
                'key' => 'two-factor',
                'label' => 'Turn on two-factor',
                'hint' => $password ? 'Adds a code from your phone at sign-in' : 'Set a password first',
                'done' => $twoFactor,
                'url' => $password ? $settings.'#2fa' : $settings.'#account',
            ],
            [
                'key' => 'recovery',
                'label' => 'Keep your recovery codes',
                'hint' => $twoFactor ? 'Make new ones when you run low' : 'They come with two-factor',
                'done' => $twoFactor && $codes > 0,
                'url' => $settings.'#2fa',
            ],
            [
                'key' => 'username',
                'label' => 'Choose a username',
                'hint' => 'Your handle in every HOP app',
                'done' => filled($user->username),
                'url' => $settings.'#account',
            ],
            [
                'key' => 'photo',
                'label' => 'Add a profile photo',
                'hint' => 'Shown in every HOP app',
                'done' => $user->avatarUrl() !== null,
                'url' => $settings.'#account',
            ],
        ];
        $done = count(array_filter($steps, fn ($s) => $s['done']));

        return ['steps' => $steps, 'done' => $done, 'total' => count($steps), 'percent' => (int) round($done / count($steps) * 100)];
    }

    /**
     * The person's latest real sign-ins (never anyone else's, never the single sign-on
     * hand-offs into the apps, which would otherwise fill the list).
     *
     * @return Collection<int, LoginHistory>
     */
    public function logins(): Collection
    {
        return LoginHistory::where('user_id', $this->user->id)->signIns()
            ->orderByDesc('logged_in_at')->orderByDesc('id')
            ->limit(self::LOGINS)
            ->get(['id', 'user_id', 'logged_in_at', 'country', 'country_name', 'city', 'device', 'browser', 'is_proxy']);
    }

    /** "Jakarta, Indonesia", "Indonesia", or null when no place was stored. */
    public static function place(LoginHistory $login): ?string
    {
        $country = $login->country ? ($login->country_name ?: country_name($login->country)) : null;

        return collect([$login->city, $country])->filter()->implode(', ') ?: null;
    }

    /** @return Collection<int, ActivityLog> */
    public function activity(): Collection
    {
        return ActivityLog::where('user_id', $this->user->id)
            ->orderByDesc('created_at')->orderByDesc('id')
            ->limit(self::ACTIVITY)
            ->get(['id', 'user_id', 'action', 'description', 'created_at']);
    }
}
