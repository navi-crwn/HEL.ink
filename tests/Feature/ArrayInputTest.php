<?php

use App\Models\HopApp;
use App\Models\Role;
use App\Models\User;
use App\Support\HopSignature;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

/*
 * ?x[]=1 and a JSON object where a string is expected are not strings: they count as absent.
 * Casting them with (string) raises "Array to string conversion", which is a 500.
 */

function arraySignedHeaders(string $slug, string $secret, string $method, string $uri, string $body = ''): array
{
    $ts = (string) time();
    $nonce = bin2hex(random_bytes(8));
    $base = HopSignature::stringToSign($slug, $ts, $nonce, $method, $uri, $body);

    return [
        'X-HOP-App' => $slug,
        'X-HOP-Timestamp' => $ts,
        'X-HOP-Nonce' => $nonce,
        'X-HOP-Signature' => HopSignature::b64(hash_hmac('sha256', $base, $secret, true)),
        'Accept' => 'application/json',
    ];
}

function arraySignedApp(array $capabilities): string
{
    $secret = 'local-test-array-input-secret-long-enough';
    HopApp::create(['slug' => 'imperium', 'name' => 'Imperium', 'url' => 'https://imperium.example.test',
        'callback_path' => '/auth/sso/callback', 'start_path' => '/login', 'status' => 'active',
        'secret' => $secret, 'capabilities' => $capabilities]);

    return $secret;
}

test('public pages ignore an array query value', function (string $uri) {
    $this->withoutVite()->get($uri)->assertOk();
})->with([
    '/?lang[]=de',
    '/faq?lang[]=de',
    '/privacy?lang[x]=y',
    '/changelog?app[]=x',
    '/changelog?app[x]=y',
    '/changelog?page[]=1',
    '/reset-password/abc?email[]=x',
    '/login?next[]=x',
    '/register?ref[]=x',
]);

test('an array lang value keeps the language in the cookie and sets no new one', function () {
    $response = $this->withoutVite()->withUnencryptedCookie('hop_lang', 'de')->get('/faq?lang[]=ru');

    $response->assertOk();
    expect($response->getContent())->toContain('<html lang="de"');
    expect(collect($response->headers->getCookies())->map->getName()->all())->not->toContain('hop_lang');
});

test('an array language cookie falls back to English', function () {
    $request = Request::create('/faq', 'GET', [], ['hop_lang' => ['de']]);

    $response = app(Kernel::class)->handle($request);

    expect($response->getStatusCode())->toBe(200);
    expect($response->getContent())->toContain('<html lang="en"');
});

test('the changelog treats an array app filter as no filter', function () {
    $response = $this->withoutVite()->get('/changelog?app[]=LinkHop')->assertOk();

    expect($response->viewData('selected'))->toBe('');
    expect($response->viewData('entries')->pluck('app')->unique()->count())->toBeGreaterThan(1);
});

test('the password reset page does not lock the email field for an array email', function () {
    $html = $this->withoutVite()->get('/reset-password/abc?email[]=x')->assertOk()->getContent();

    expect($html)->not->toContain('readonly');
});

test('sso refuses an array state or return address with 400', function (string $query) {
    config(['hop.sso_secret' => 'test-secret-that-is-long-enough-1234567890', 'hop.apps.linkhop.url' => 'https://link.example.com', 'hop.apps.linkhop.secret' => 'per-app-secret-that-is-long-enough-0987654321']);
    $this->actingAs(User::factory()->create())->withSession(['2fa_verified' => true])
        ->get('/sso/linkhop?'.$query)->assertStatus(400);
})->with([
    'state[]=x&return_to=https://link.example.com/auth/sso/callback',
    'state=abcdefghijklmnop1234&return_to[]=y',
    'state[]=x&return_to[]=y',
]);

test('the app launcher ignores an array target page', function () {
    config(['hop.sso_secret' => 'test-secret-that-is-long-enough-1234567890', 'hop.apps.linkhop.url' => 'https://link.example.com', 'hop.apps.linkhop.secret' => 'per-app-secret-that-is-long-enough-0987654321']);
    $this->actingAs(User::factory()->create())->withSession(['2fa_verified' => true])
        ->get('/apps/linkhop?to[]=x')->assertRedirect('https://link.example.com/auth/sso');
});

test('signed-in pages ignore array query values', function (string $uri) {
    $user = User::factory()->create();
    $user->forceFill(['email_verified_at' => now()])->save();
    $this->withoutVite()->actingAs($user)->withSession(['2fa_verified' => true])->get($uri)->assertOk();
})->with([
    '/settings?activity_from[]=2026-01-01',
    '/settings?activity_to[x]=2026-01-01',
    '/profile/username-check?u[]=x',
]);

test('the admin user list ignores an array search', function () {
    $admin = User::factory()->create();
    $admin->roles()->create(['role' => Role::OWNER, 'app_slug' => null]);
    $this->withoutVite()->actingAs($admin)->withSession(['2fa_verified' => true])
        ->get('/admin/users?q[]=x&status[]=suspended')->assertOk();
});

test('the signed admin API ignores array query values', function (string $uri) {
    $secret = arraySignedApp(['admin_client']);
    $this->withHeaders(arraySignedHeaders('imperium', $secret, 'GET', $uri))->get($uri)->assertOk();
})->with([
    '/api/hop/users?q[]=x',
    '/api/hop/users?status[]=active',
    '/api/hop/users?role[]=owner',
    '/api/hop/users?limit[]=1&cursor[]=1',
    '/api/hop/audit?target[]=1',
    '/api/hop/search?q[]=x',
    '/api/hop/stats?since[]=x&until[]=y',
]);

test('the signed profiles API ignores array query values', function (string $uri) {
    $secret = arraySignedApp(['admin_client']);
    User::factory()->create();
    $this->withHeaders(arraySignedHeaders('imperium', $secret, 'GET', $uri))->get($uri)
        ->assertOk()->assertJsonPath('ok', true)->assertJsonCount(1, 'data');
})->with([
    '/api/hop/profiles?ids[]=999',
    '/api/hop/profiles?since[]=2999-01-01',
]);

test('the signed admin action answers unknown_action for an array action', function () {
    $secret = arraySignedApp(['admin_client']);
    $user = User::factory()->create();
    $uri = '/api/hop/users/'.$user->id.'/actions';
    $body = json_encode(['action' => ['suspend']]);
    $this->call('POST', $uri, [], [], [], $this->transformHeadersToServerVars(arraySignedHeaders('imperium', $secret, 'POST', $uri, $body) + ['Content-Type' => 'application/json']), $body)
        ->assertStatus(422)->assertJsonPath('error', 'unknown_action');
    expect($user->fresh()->status)->not->toBe(User::STATUS_SUSPENDED);
});

test('the signed account API treats object fields as absent', function (string $path, array $fields, int $status) {
    $secret = arraySignedApp(['account_api']);
    $uri = '/api/apps/imperium/'.$path;
    $body = json_encode($fields);
    $this->call('POST', $uri, [], [], [], $this->transformHeadersToServerVars(arraySignedHeaders('imperium', $secret, 'POST', $uri, $body) + ['Content-Type' => 'application/json']), $body)
        ->assertStatus($status);
})->with([
    'login email' => ['login', ['email' => ['x'], 'password' => 'p'], 401],
    'login password' => ['login', ['email' => 'a@example.test', 'password' => ['x']], 401],
    'login ip' => ['login', ['email' => 'a@example.test', 'password' => 'p', 'ip' => ['x']], 401],
    'register fields' => ['register', ['name' => ['x'], 'email' => ['x'], 'password' => ['x'], 'tos_accepted' => true, 'terms_version' => ['x']], 422],
]);

test('a form post with an array field answers with a validation error, not a 500', function (string $from, string $path, array $fields) {
    $this->withoutVite()->from($from)->followingRedirects()->post($path, $fields)->assertOk();
})->with([
    'login email' => ['/login', '/login', ['email' => ['x'], 'password' => 'p']],
    'login turnstile' => ['/login', '/login', ['email' => 'a@example.test', 'password' => 'p', 'cf-turnstile-response' => ['x']]],
    'register email' => ['/register', '/register', ['name' => ['x'], 'email' => ['x'], 'password' => ['x']]],
    'forgot email' => ['/forgot-password', '/forgot-password', ['email' => ['x']]],
    'reset email' => ['/reset-password/abc', '/reset-password', ['email' => ['x'], 'token' => ['x'], 'password' => ['x']]],
]);

test('a signed-in form post with an array field answers with a validation error, not a 500', function (string $method, string $from, string $path, array $fields) {
    $user = User::factory()->create();
    $this->withoutVite()->actingAs($user)->withSession(['2fa_verified' => true])->from($from)->followingRedirects()
        ->call($method, $path, $fields)->assertOk();
})->with([
    'profile name' => ['PATCH', '/settings', '/profile', ['name' => ['x'], 'email' => 'a@example.test']],
    'profile email' => ['PATCH', '/settings', '/profile', ['name' => 'A', 'email' => ['x']]],
    'profile username' => ['PATCH', '/settings', '/profile', ['name' => 'A', 'email' => 'a@example.test', 'username' => ['x']]],
    'profile website' => ['PATCH', '/settings', '/profile', ['name' => 'A', 'email' => 'a@example.test', 'website' => ['x']]],
    'profile bio' => ['PATCH', '/settings', '/profile', ['name' => 'A', 'email' => 'a@example.test', 'bio' => ['x'], 'location' => ['y']]],
    'profile back' => ['PATCH', '/settings', '/profile', ['name' => 'A', 'email' => 'a@example.test', '_back' => ['x']]],
    'delete password' => ['DELETE', '/settings', '/profile', ['password' => ['x']]],
    'change password' => ['PUT', '/settings', '/password', ['current_password' => ['x']]],
]);

test('confirming a password with an array value is refused, not a 500', function () {
    $this->withoutVite()->actingAs(User::factory()->create())->withSession(['2fa_verified' => true])
        ->from('/confirm-password')->post('/confirm-password', ['password' => ['x']])->assertSessionHasErrors('password');
});

test('an admin announcement post with an array field answers with a validation error, not a 500', function () {
    $admin = User::factory()->create();
    $admin->roles()->create(['role' => Role::OWNER, 'app_slug' => null]);
    $this->withoutVite()->actingAs($admin)->withSession(['2fa_verified' => true])->from('/admin/announcements')->followingRedirects()
        ->post('/admin/announcements', ['title' => ['x'], 'body' => ['y']])->assertOk();
});
