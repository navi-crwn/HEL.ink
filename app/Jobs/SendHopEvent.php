<?php

namespace App\Jobs;

use App\Models\HopApp;
use App\Support\HopSignature;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Tells one app that something happened to an account (HOP App Protocol §6):
 * POST {app}/hop/events with {id, type, at, data}, signed with the app's
 * secret. Retried with backoff for about a day; the app must be idempotent on
 * `id`. Only apps that declare the `events` capability get these.
 */
class SendHopEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 8;

    /** @return list<int> seconds */
    public function backoff(): array
    {
        return [30, 120, 600, 1800, 3600, 7200, 14400, 28800];
    }

    public function __construct(
        public readonly int $appId,
        public readonly string $type,
        public readonly array $data,
        public readonly string $eventId = '',
        public readonly string $at = '',
    ) {}

    /**
     * Queue the event for every app that listens.
     *
     * @return list<string> slugs of the apps whose job was queued; a queue that cannot take a job throws
     */
    public static function broadcast(string $type, array $data): array
    {
        try {
            $apps = HopApp::registered()->get(); // hidden apps (Imperium) may listen too
        } catch (\Throwable) {
            return []; // table missing on a fresh install
        }
        $queued = [];
        $id = (string) Str::uuid();
        $at = now()->toIso8601String();
        foreach ($apps as $app) {
            if ($app->isConfigured() && $app->can('events')) {
                self::dispatch($app->id, $type, $data, $id, $at);
                $queued[] = (string) $app->slug;
            }
        }

        return $queued;
    }

    public function handle(): void
    {
        $app = HopApp::find($this->appId);
        if (! $app || ! $app->isConfigured() || ! $app->can('events')) {
            return;
        }
        $body = json_encode(['id' => $this->eventId, 'type' => $this->type, 'at' => $this->at, 'data' => $this->data], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $path = '/hop/events';
        $res = Http::timeout(10)->withHeaders(HopSignature::headers($app, 'POST', $path, $body) + ['Content-Type' => 'application/json'])
            ->withBody($body, 'application/json')->post($app->baseUrl().$path);
        if (! $res->successful()) {
            Log::warning('HOP event not accepted', ['app' => $app->slug, 'type' => $this->type, 'status' => $res->status()]);
            $res->throw();
        }
        Cache::forget(self::failureKey($app->slug));
        Cache::forget(self::failureKey($app->slug).':last');
    }

    /** Cache key of the count of deliveries to an app that failed for good (a counter; `:last` beside it holds the time and type; read by EventRegistry::warnings()). */
    public static function failureKey(string $slug): string
    {
        return 'hop:event-failures:'.$slug;
    }

    /**
     * After the last retry: remember, per app, that events did not arrive (how many, the last time and type), so the
     * hub's event warnings can say so. Nothing about the person is kept: no id, no address.
     */
    public function failed(\Throwable $e): void
    {
        $app = HopApp::find($this->appId);
        if (! $app) {
            return;
        }
        $key = self::failureKey((string) $app->slug);
        // Atomic: several jobs can fail at the same moment.
        if (! is_int(Cache::get($key, 0))) {
            Cache::forget($key); // a leftover of an older format
        }
        Cache::add($key, 0);
        Cache::increment($key);
        Cache::forever($key.':last', ['at' => now()->toIso8601String(), 'type' => $this->type]);
    }
}
