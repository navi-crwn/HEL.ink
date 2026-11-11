<?php

namespace App\Services;

use App\Jobs\EnrichLoginRecord;
use App\Models\LoginHistory;
use App\Models\User;

use function Illuminate\Support\defer;

/**
 * Records a sign-in (password, Google, or into one of the HOP apps) on the user and in
 * login_histories. The row is written at once with what the request already knows (account,
 * address, browser, how). The place and proxy lookups call outside services, so they are queued
 * (EnrichLoginRecord, the database queue served by hub-queue) and fill the row in afterwards;
 * if the queue cannot take the job they run after the response instead.
 */
class LoginRecorder
{
    public function __construct(
        private readonly UserAgentService $userAgent,
    ) {}

    /**
     * @param  string  $via  "password", "google", or an app name ("PixelHop") for a single sign-on
     *                       hand-off into that app, which is kept apart from the real sign-ins
     */
    public function record(User $user, string $ip, ?string $userAgent, string $via = 'password'): void
    {
        $kind = in_array($via, ['password', 'google'], true) ? LoginHistory::KIND_LOGIN : LoginHistory::KIND_SSO;
        $uaDetails = $this->userAgent->parse($userAgent);
        $user->forceFill([
            'last_login_ip' => $ip,
            'last_login_at' => now(),
            'last_login_device' => $uaDetails['device'],
            'last_login_browser' => $uaDetails['browser'],
        ])->saveQuietly();
        $history = LoginHistory::create([
            'user_id' => $user->id,
            'kind' => $kind,
            'ip_address' => $ip,
            'provider' => $via === 'password' ? null : $via,
            'device' => $uaDetails['device'],
            'browser' => $uaDetails['browser'],
            'logged_in_at' => now(),
        ]);
        LoginHistory::keepLatest($user->id);

        $job = new EnrichLoginRecord($history->id, $user->id, $ip, $via);
        try {
            dispatch($job);
        } catch (\Throwable $e) {
            report($e);
            defer(function () use ($job) {
                try {
                    $job->handle(app(GeoIpService::class), app(ProxyDetectionService::class));
                } catch (\Throwable $e) {
                    // One try, then dropped; the row is written. The address stays out of the log.
                    \Log::warning('Sign-in lookup dropped', ['history' => $job->historyId, 'error' => class_basename($e)]);
                }
            });
        }
    }
}
