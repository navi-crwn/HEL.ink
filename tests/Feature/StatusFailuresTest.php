<?php

use App\Models\ServiceCheck;
use App\Services\Watchdog;

function failedCheck(string $slug, $at): void
{
    ServiceCheck::create(['app_slug' => $slug, 'kind' => 'health', 'ok' => false, 'status_code' => 500, 'latency_ms' => 10, 'error' => 'boom', 'checked_at' => $at]);
}

test('the status page shows a quiet empty state without failures', function () {
    $this->get('/status')->assertOk()->assertSee('No failures in the last 24 hours');
});

test('failures are grouped per app with a count, the last time and a 24 cell hourly strip', function () {
    failedCheck('linkhop', now()->subMinutes(5));
    failedCheck('linkhop', now()->subMinutes(20));
    failedCheck('linkhop', now()->subHours(5)->subMinutes(1));
    failedCheck('biohop', now()->subHours(30)); // older than 24 hours: not counted

    $f = app(Watchdog::class)->failures();
    expect($f->keys()->all())->toBe(['linkhop'])
        ->and($f['linkhop']['count'])->toBe(3)
        ->and($f['linkhop']['hours'])->toHaveCount(24)
        ->and(array_sum($f['linkhop']['hours']))->toBe(3)
        ->and($f['linkhop']['hours'][23] + $f['linkhop']['hours'][22])->toBe(2);

    $this->get('/status')->assertOk()->assertSee('Last failure')->assertDontSee('No failures in the last 24 hours');
});
