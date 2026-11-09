<?php

use App\Models\HopApp;
use App\Support\EventRegistry;
use Database\Seeders\HopAppsSeeder;
use Illuminate\Support\Facades\DB;

/**
 * GET /api/hop/apps reports LinkHop's capabilities from its registry row. They used to be a
 * list written in the controller, which hid whatever the row said.
 */
const REG_IMPERIUM_SECRET = 'imperium-secret-that-is-long-enough-1234567890';

function registryAs(string $app, string $secret)
{
    $path = '/api/hop/apps';
    $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    $ts = (string) time();
    $nonce = $b64(random_bytes(16));
    $base = implode('|', ['hop1', $app, $ts, $nonce, 'GET', $path, hash('sha256', '')]);

    // A plain GET: getJson() would send "[]" as the body, and the signature covers the (empty) body.
    return test()->withHeaders([
        'X-HOP-App' => $app, 'X-HOP-Timestamp' => $ts, 'X-HOP-Nonce' => $nonce, 'X-HOP-Actor' => '1',
        'X-HOP-Signature' => $b64(hash_hmac('sha256', $base, $secret, true)), 'Accept' => 'application/json',
    ])->get($path);
}

beforeEach(function () {
    HopApp::create([
        'slug' => 'imperium', 'name' => 'Imperium', 'url' => 'https://imperium.example.com',
        'callback_path' => '/auth/sso/callback', 'start_path' => '/login', 'status' => 'active',
        'secret' => REG_IMPERIUM_SECRET, 'capabilities' => ['admin_client'], 'hidden' => true,
    ]);
    config(['hop.apps.linkhop.url' => 'https://links.example.test', 'hop.apps.linkhop.enabled' => true]);
});

test('the linkhop entry carries the capabilities of its registry row, nothing added and nothing dropped', function () {
    HopApp::factory()->create([
        'slug' => 'linkhop', 'name' => 'LinkHop', 'url' => 'https://old-hub.example.test', 'status' => 'maintenance',
        'capabilities' => ['events', 'admin_api', 'reports'], 'secret' => 'linkhop-row-secret-that-is-long-enough-12345',
    ]);

    $links = collect(registryAs('imperium', REG_IMPERIUM_SECRET)->assertOk()->json('data'))->where('slug', 'linkhop')->values();

    expect($links)->toHaveCount(1)
        ->and($links[0]['capabilities'])->toBe(['events', 'admin_api', 'reports'])
        ->and($links[0]['capabilities'])->not->toContain('metrics')
        ->and($links[0]['status'])->toBe('maintenance')
        // The address stays LinkHop's own configured origin, and no secret is handed out for it, as before.
        ->and($links[0]['url'])->toBe('https://links.example.test')
        ->and($links[0])->not->toHaveKey('secret');
});

test('without a linkhop row the entry is what the seeder would create', function () {
    $links = collect(registryAs('imperium', REG_IMPERIUM_SECRET)->assertOk()->json('data'))->where('slug', 'linkhop')->values();

    expect($links)->toHaveCount(1)
        ->and($links[0]['capabilities'])->toBe(EventRegistry::capabilitiesFor('linkhop'))
        ->and($links[0]['capabilities'])->toContain('events')->toContain('admin_api')->toContain('metrics')
        ->and($links[0]['url'])->toBe('https://links.example.test');

    config(['hop.sso_secret' => 'shared-sso-secret-that-is-long-enough-123456']);
    $this->seed(HopAppsSeeder::class);
    expect(HopApp::where('slug', 'linkhop')->first()->capabilities)->toBe($links[0]['capabilities']);
});

test('seeding never rewrites the capabilities of a row that exists', function () {
    config(['hop.sso_secret' => 'shared-sso-secret-that-is-long-enough-123456']);
    $row = HopApp::factory()->create(['slug' => 'linkhop', 'capabilities' => ['events', 'custom_one']]);
    $pixel = HopApp::factory()->pixelhop()->create(['capabilities' => []]);

    expect(EventRegistry::ensure())->toMatchArray(['linkhop' => 'exists', 'pixelhop' => 'exists']);

    expect($row->fresh()->capabilities)->toBe(['events', 'custom_one'])
        ->and($pixel->fresh()->capabilities)->toBe([]);
});

test('the migration gives an existing linkhop row the two capabilities the controller used to hard-code, once', function () {
    $row = HopApp::factory()->create(['slug' => 'linkhop', 'capabilities' => ['account_api', 'metrics']]);
    $other = HopApp::factory()->notehop()->create(['capabilities' => ['events']]);
    $migration = require base_path('database/migrations/2026_11_09_000003_linkhop_row_carries_its_capabilities.php');

    $migration->up();
    $after = DB::table('apps')->where('id', $row->id)->first();
    $migration->up();

    expect($row->fresh()->capabilities)->toBe(['account_api', 'metrics', 'admin_api'])
        ->and(DB::table('apps')->where('id', $row->id)->first())->toEqual($after)
        ->and($other->fresh()->capabilities)->toBe(['events']);
});

test('the migration does nothing when there is no linkhop row', function () {
    $migration = require base_path('database/migrations/2026_11_09_000003_linkhop_row_carries_its_capabilities.php');
    $migration->up();

    expect(HopApp::where('slug', 'linkhop')->exists())->toBeFalse();
});
