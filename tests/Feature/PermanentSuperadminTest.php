<?php

use App\Http\Controllers\Api\HopAdminController;
use App\Models\HopApp;
use App\Models\Role;
use App\Models\User;
use App\Services\UserDeletion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Password;
use Symfony\Component\HttpKernel\Exception\HttpException;

test('every admin action refuses a permanent superadmin for self and other actors', function () {
    $owner = User::factory()->create();
    $owner->roles()->create(['role' => Role::OWNER, 'app_slug' => null]);
    $other = User::factory()->create();
    $before = $owner->fresh()->getAttributes();
    foreach ([$owner, $other] as $actor) {
        foreach (['suspend', 'activate', 'delete', 'set_roles', 'revoke_sessions', 'revoke_tokens', 'note'] as $action) {
            $request = Request::create('/api/hop/users/'.$owner->id.'/actions', 'POST', ['action' => $action, 'params' => ['roles' => [], 'text' => 'Forbidden']]);
            $request->headers->set('X-HOP-Actor', (string) $actor->id);
            $request->attributes->set('hop_app', new HopApp(['slug' => 'imperium']));
            $response = app(HopAdminController::class)->act($request, $owner->fresh(), app(UserDeletion::class));
            expect($response->getStatusCode())->toBe(403);
            expect($response->getData(true)['error'])->toBe('owner_protected');
        }
    }
    expect($owner->fresh()->getAttributes())->toBe($before);
    expect($owner->fresh()->isSuperAdmin())->toBeTrue();
    expect(DB::table('hop_user_notes')->where('user_id', $owner->id)->count())->toBe(0);
});

test('configured superadmin is protected without an explicit owner role', function () {
    $owner = User::factory()->create();
    config(['auth.superadmin_email' => $owner->email]);
    $this->actingAs($owner)->patchJson('/profile', ['name' => 'Changed'])->assertForbidden();
    expect($owner->fresh()->name)->not->toBe('Changed');
});

test('signed admin HTTP requests and valid password reset cannot mutate an owner', function () {
    $owner = User::factory()->create();
    $owner->roles()->create(['role' => Role::OWNER, 'app_slug' => null]);
    $secret = 'local-test-imperium-secret-long-enough';
    HopApp::create(['slug' => 'imperium', 'name' => 'Imperium', 'url' => 'https://imperium.example.test',
        'callback_path' => '/auth/sso/callback', 'start_path' => '/login', 'status' => 'active',
        'secret' => $secret, 'capabilities' => ['admin_client']]);
    $path = '/api/hop/users/'.$owner->id.'/actions';
    $body = json_encode(['action' => 'set_roles', 'params' => ['roles' => []]]);
    $b64 = fn ($s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    $ts = (string) time();
    $nonce = $b64(random_bytes(16));
    $signature = $b64(hash_hmac('sha256', implode('|', ['hop1', 'imperium', $ts, $nonce, 'POST', $path, hash('sha256', $body)]), $secret, true));
    $this->call('POST', $path, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_HOP_APP' => 'imperium', 'HTTP_X_HOP_TIMESTAMP' => $ts, 'HTTP_X_HOP_NONCE' => $nonce,
        'HTTP_X_HOP_SIGNATURE' => $signature, 'HTTP_X_HOP_ACTOR' => (string) $owner->id], $body)
        ->assertForbidden()->assertJsonPath('error', 'owner_protected');
    $password = $owner->password;
    $token = Password::createToken($owner);
    $this->post('/reset-password', ['token' => $token, 'email' => $owner->email,
        'password' => 'Local-reset-test-123!', 'password_confirmation' => 'Local-reset-test-123!'])->assertForbidden();
    expect($owner->fresh()->password)->toBe($password);
    expect($owner->fresh()->isSuperAdmin())->toBeTrue();
});

test('self service and legacy admin mutations cannot modify a superadmin', function () {
    $owner = User::factory()->create();
    $owner->roles()->create(['role' => Role::OWNER, 'app_slug' => null]);
    $this->actingAs($owner)->withSession(['2fa_verified' => true]);
    foreach ([['PATCH', '/profile'], ['POST', '/profile/avatar'], ['DELETE', '/profile/avatar'], ['DELETE', '/profile'], ['PUT', '/password'], ['POST', '/api-tokens'], ['POST', '/settings/2fa/enable'], ['DELETE', '/settings/2fa/disable'], ['POST', '/onboarding/set-password']] as [$method, $path]) {
        $this->json($method, $path)->assertForbidden();
    }
    foreach (['suspend', 'activate', 'role'] as $action) {
        $this->postJson('/admin/users/'.$owner->id.'/'.$action)->assertForbidden();
    }
    $this->deleteJson('/admin/users/'.$owner->id)->assertForbidden();
    $this->get('/profile')->assertOk()->assertSee('permanent and read-only')->assertDontSee('name="name"', false);
    Http::fake();
    try {
        app(UserDeletion::class)->delete($owner);
        $this->fail('Deletion must be refused');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(403);
    }
    Http::assertNothingSent();
    expect($owner->fresh())->not->toBeNull();
});
