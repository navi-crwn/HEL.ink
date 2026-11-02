<?php

namespace App\Services\ProxyDetection\Detectors;

use App\Services\ProxyDetection\ProxyDetectorInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class BaseProxyDetector implements ProxyDetectorInterface
{
    protected int $timeout = 5;

    protected function makeRequest(string $url, array $params = []): ?array
    {
        try {
            $response = Http::timeout($this->timeout)->get($url, $params);
            if ($response->successful()) {
                return $response->json();
            }
            // Host only: the URL contains the visitor's IP (and, for some providers, the API key).
            Log::debug("{$this->getName()} request failed", [
                'host' => parse_url($url, PHP_URL_HOST),
                'status' => $response->status(),
            ]);

            return null;
        } catch (\Throwable $e) {
            Log::debug("{$this->getName()} request error", [
                'host' => parse_url($url, PHP_URL_HOST),
                'error' => str_replace($url, '[url]', $e->getMessage()),
            ]);

            return null;
        }
    }

    public function isAvailable(): bool
    {
        return true;
    }
}
