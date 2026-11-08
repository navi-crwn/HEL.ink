<?php

use App\Models\HopApp;
use App\Support\ControlRegistry;
use App\Support\HopApps;
use Database\Seeders\HopAppsSeeder;
use Illuminate\Support\Facades\DB;

/*
 * Self-hosting: a fresh hub registers Imperium (the optional admin panel) from the environment, and
 * the app rows the seeder adds carry the capabilities Imperium and the apps' own sign-in forms need.
 */

const CONTROL_SECRET = 'imperium-secret-that-is-long-enough-1234567';

beforeEach(function () {
    config()->set('hop.sso_secret', 'shared-secret-that-is-long-enough-1234567890');
    config()->set('hop.control_url', 'https://imperium.example.com');
    config()->set('hop.control_secret', CONTROL_SECRET);
});

test('the seeder registers Imperium as a hidden admin client when its address and secret are set', function () {
    $this->seed(HopAppsSeeder::class);

    $row = HopApp::where('slug', 'imperium')->first();
    expect($row)->not->toBeNull()
        ->and($row->url)->toBe('https://imperium.example.com')
        ->and($row->secret)->toBe(CONTROL_SECRET)
        ->and($row->hidden)->toBeTrue()
        ->and($row->can('admin_client'))->toBeTrue()
        ->and($row->callback_path)->toBe('/auth/sso/callback')
        ->and($row->start_path)->toBe('/auth/sso')
        ->and(collect(HopApps::all())->pluck('slug'))->not->toContain('imperium');
});

test('without a secret, or without an address, Imperium is skipped and nothing is written', function () {
    config()->set('hop.control_secret', 'too-short');
    expect(ControlRegistry::ensure())->toStartWith('skipped')
        ->and(HopApp::where('slug', 'imperium')->exists())->toBeFalse();

    config()->set('hop.control_secret', CONTROL_SECRET);
    config()->set('hop.control_url', '');
    expect(ControlRegistry::ensure())->toStartWith('skipped')
        ->and(HopApp::where('slug', 'imperium')->exists())->toBeFalse();
});

test('an existing Imperium row is left exactly as it is', function () {
    $existing = HopApp::create([
        'slug' => 'imperium', 'name' => 'Imperium', 'url' => 'https://control.example.org', 'callback_path' => '/auth/sso/callback',
        'start_path' => '/auth/sso', 'pages' => ['default' => '/'], 'continue_page' => 'default', 'status' => HopApp::STATUS_ACTIVE,
        'secret' => 'owner-set-secret-that-is-long-enough-123456', 'capabilities' => ['admin_client'], 'sort_order' => 1, 'hidden' => true,
    ]);
    $before = DB::table('apps')->where('id', $existing->id)->first();

    expect(ControlRegistry::ensure())->toBe('exists')
        ->and(DB::table('apps')->where('id', $existing->id)->first())->toEqual($before);
});

test('new app rows carry admin_api, so Imperium can manage them, and PixelHop and BioHop keep account_api', function () {
    foreach (['pixelhop' => 'pixel', 'notehop' => 'note', 'filehop' => 'file', 'linkhop' => 'link', 'biohop' => 'bio'] as $slug => $host) {
        config()->set("hop.apps.{$slug}.url", "https://{$host}.example.com");
        config()->set("hop.apps.{$slug}.enabled", true);
    }
    config()->set('hop.event_apps.notehop.secret', 'notehop-secret-that-is-long-enough-12345');
    config()->set('hop.event_apps.filehop.secret', 'filehop-secret-that-is-long-enough-12345');

    $this->seed(HopAppsSeeder::class);

    foreach (['pixelhop', 'notehop', 'filehop', 'linkhop', 'biohop'] as $slug) {
        $row = HopApp::where('slug', $slug)->first();
        expect($row)->not->toBeNull()
            ->and($row->can('admin_api'))->toBeTrue()
            ->and($row->can('events'))->toBeTrue();
    }
    expect(HopApp::where('slug', 'pixelhop')->first()->can('account_api'))->toBeTrue()
        ->and(HopApp::where('slug', 'biohop')->first()->can('account_api'))->toBeTrue();
});
