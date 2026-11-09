<?php

namespace App\Services;

use App\Models\LoginHistory;
use App\Models\User;

use function Illuminate\Support\defer;

/**
 * Records a sign-in (password, Google, or into one of the HOP apps) on the
 * user and in login_histories. GeoIP and proxy checks can be slow for a new
 * IP, so the work runs after the response.
 */
class LoginRecorder
{
    public function __construct(
        private readonly GeoIpService $geoIp,
        private readonly UserAgentService $userAgent,
        private readonly ProxyDetectionService $proxy,
    ) {}

    /**
     * @param  string  $via  "password", "google", or an app name ("PixelHop") for a single sign-on
     *                       hand-off into that app, which is kept apart from the real sign-ins
     */
    public function record(User $user, string $ip, ?string $userAgent, string $via = 'password'): void
    {
        $kind = in_array($via, ['password', 'google'], true) ? LoginHistory::KIND_LOGIN : LoginHistory::KIND_SSO;
        defer(function () use ($user, $ip, $userAgent, $via, $kind) {
            $geoDetails = $this->geoIp->details($ip);
            $uaDetails = $this->userAgent->parse($userAgent);
            $proxyResult = $this->proxy->detect($ip);
            $user->forceFill([
                'last_login_ip' => $ip,
                'last_login_at' => now(),
                'last_login_country' => $geoDetails['country'],
                'last_login_city' => $geoDetails['city'],
                'last_login_provider' => $geoDetails['provider'],
                'last_login_device' => $uaDetails['device'],
                'last_login_browser' => $uaDetails['browser'],
            ])->saveQuietly();
            LoginHistory::create([
                'user_id' => $user->id,
                'kind' => $kind,
                'ip_address' => $ip,
                'country' => $geoDetails['country'],
                'country_name' => $geoDetails['country_name'] ?? null,
                'city' => $geoDetails['city'],
                'region' => $geoDetails['region'] ?? null,
                'provider' => $via === 'password' ? $geoDetails['provider'] : $via,
                'isp' => $geoDetails['isp'] ?? null,
                'device' => $uaDetails['device'],
                'browser' => $uaDetails['browser'],
                'is_proxy' => $proxyResult['is_proxy'],
                'proxy_type' => $proxyResult['type'],
                'proxy_confidence' => $proxyResult['confidence'],
                'logged_in_at' => now(),
            ]);
            LoginHistory::keepLatest($user->id);
        });
    }
}
