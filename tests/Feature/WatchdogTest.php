<?php

use App\Mail\ServiceAlert;
use App\Models\ServiceCheck;
use App\Services\Watchdog;
use App\Support\HopApp;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    config()->set('hop.apps.linkhop.url', 'https://link.example.com');
    config()->set('hop.apps.biohop.enabled', false);
    config()->set('hop.apps.pixelhop.enabled', false);
    config()->set('hop.apps.notehop.enabled', false);
    config()->set('hop.watch.alert_email', 'owner@example.com');
    config()->set('hop.watch.telegram_token', null);
});

test('hop:watch records a healthy app', function () {
    Http::fake(['https://link.example.com/up' => Http::response('', 200)]);
    $this->artisan('hop:watch')->assertSuccessful();
    $check = ServiceCheck::sole();
    expect($check->app_slug)->toBe('linkhop')->and($check->ok)->toBeTrue()->and($check->status_code)->toBe(200);
    expect(app(Watchdog::class)->summary()['linkhop']['ok'])->toBeTrue();
});

test('three failures in a row raise one alert, recovery sends another', function () {
    Mail::fake();
    $code = 503;
    Http::fake(function () use (&$code) {
        return Http::response('', $code);
    });
    foreach (range(1, 4) as $i) {
        $this->artisan('hop:watch')->assertFailed();
    }
    Mail::assertSent(ServiceAlert::class, 1);
    Mail::assertSent(ServiceAlert::class, fn ($m) => str_contains($m->subjectLine, 'down'));
    $code = 200;
    $this->artisan('hop:watch')->assertSuccessful();
    Mail::assertSent(ServiceAlert::class, 2);
    expect(ServiceCheck::count())->toBe(5);
});

test('a functional check url is probed too and a timeout counts as down', function () {
    config()->set('hop.apps.linkhop.check', 'https://link.example.com/l/health-check');
    Http::fake([
        'https://link.example.com/up' => Http::response('', 200),
        'https://link.example.com/l/health-check' => fn () => throw new ConnectionException('timed out'),
    ]);
    $this->artisan('hop:watch')->assertFailed();
    expect(ServiceCheck::where('kind', 'check')->sole()->error)->toContain('timed out');
    $this->withoutVite()->get('/status')->assertOk()->assertSee('timed out');
    $this->getJson('/status.json')->assertOk()->assertJsonPath('apps.linkhop.ok', true);
});

test('status.json never exposes an app secret or internal endpoint', function () {
    config()->set('hop.apps.linkhop.secret', str_repeat('s', 40));
    config()->set('hop.apps.linkhop.url', 'https://link.example.test');

    $json = $this->getJson('/status.json')->assertOk();
    $body = $json->getContent();

    expect($body)->not->toContain(str_repeat('s', 40))
        ->not->toContain('"secret"')
        ->not->toContain('"callback"')
        ->not->toContain('"logout"');
    $json->assertJsonPath('apps.linkhop.slug', 'linkhop');
});

test('an app object never serializes its secret', function () {
    $app = HopApp::fromConfig('demo', ['url' => 'https://demo.test', 'secret' => str_repeat('k', 40)]);

    expect(json_encode($app))->not->toContain(str_repeat('k', 40))
        ->and(print_r($app, true))->not->toContain(str_repeat('k', 40));
});
