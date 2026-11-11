<?php

namespace App\Jobs;

use App\Models\LoginHistory;
use App\Models\User;
use App\Services\GeoIpService;
use App\Services\ProxyDetectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Adds the place and the proxy check to a sign-in that LoginRecorder already wrote. The GeoIP and
 * proxy lookups call outside services, so they run on the queue and never hold up a sign-in. If
 * they all fail the row stays as it was: who, from which address, with which browser, and how.
 */
class EnrichLoginRecord implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /** @return list<int> seconds */
    public function backoff(): array
    {
        return [30, 300];
    }

    public function __construct(
        public readonly int $historyId,
        public readonly int $userId,
        public readonly string $ip,
        public readonly string $via = 'password',
    ) {}

    /**
     * The payload carries an IP address, so a job that fails for good is not left in failed_jobs: on the last
     * attempt the failure is logged (without the address) and the job ends. The login row is already written.
     * Earlier attempts throw, so the queue retries.
     */
    public function handle(GeoIpService $geoIp, ProxyDetectionService $proxy): void
    {
        try {
            $this->enrich($geoIp, $proxy);
        } catch (\Throwable $e) {
            if ($this->attempts() < $this->tries) {
                throw $e;
            }
            Log::warning('Sign-in lookup dropped after the last attempt', ['history' => $this->historyId, 'error' => class_basename($e)]);
        }
    }

    private function enrich(GeoIpService $geoIp, ProxyDetectionService $proxy): void
    {
        $history = LoginHistory::find($this->historyId);
        if (! $history) {
            return;
        }
        $geo = $geoIp->details($this->ip);
        $proxyResult = $proxy->detect($this->ip);
        $history->forceFill([
            'country' => $geo['country'],
            'country_name' => $geo['country_name'] ?? null,
            'city' => $geo['city'],
            'region' => $geo['region'] ?? null,
            'provider' => $this->via === 'password' ? $geo['provider'] : $this->via,
            'isp' => $geo['isp'] ?? null,
            'is_proxy' => $proxyResult['is_proxy'],
            'proxy_type' => $proxyResult['type'],
            'proxy_confidence' => $proxyResult['confidence'],
        ])->save();

        // The account's "last sign-in" line follows its newest sign-in only.
        $newest = LoginHistory::where('user_id', $this->userId)->where('kind', $history->kind)->max('id');
        if ((int) $newest === $history->id) {
            User::whereKey($this->userId)->toBase()->update([
                'last_login_country' => $geo['country'],
                'last_login_city' => $geo['city'],
                'last_login_provider' => $geo['provider'],
            ]);
        }
    }
}
