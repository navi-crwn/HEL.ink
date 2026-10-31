<?php

use App\Models\HopApp;
use App\Models\LegalAcceptance;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\HopSignature;
use App\Support\Legal;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Laravel\Socialite\Facades\Socialite;

/**
 * Batch A: server-side legal acceptance on every NEW account creation path of
 * the hub (web registration, Google OAuth new account, app-delegated
 * registration). The gate is inactive by default: with no published/required
 * terms version configured, every existing flow keeps working unchanged and
 * nothing is recorded. Tests activate a synthetic published version via
 * config(); this never touches published marketing text.
 *
 * Existing sign-in and account-linking flows must stay untouched: no consent
 * is demanded at login, and OAuth linking an existing email account is not
 * account takeover (Google vouches for the address) nor new-user consent.
 */
const LEGAL_VERSION = '2026-01-01.1';
const LEGAL_APP_SECRET = 'synthetic-pixelhop-secret-that-is-long-enough';

function signedAppRegister(array $payload): TestResponse
{
    HopApp::factory()->pixelhop(LEGAL_APP_SECRET)->create(['capabilities' => ['account_api']]);
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $timestamp = (string) time();
    $signature = HopSignature::b64(hash_hmac('sha256', 'pixelhop-api|'.$timestamp.'|'.$body, LEGAL_APP_SECRET, true));

    return test()->call('POST', '/api/pixelhop/register', [], [], [], test()->transformHeadersToServerVars([
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
        'X-PixelHop-Timestamp' => $timestamp,
        'X-PixelHop-Signature' => $signature,
    ]), $body);
}

function legalFixture(string $version = LEGAL_VERSION): string
{
    return file_get_contents(__DIR__.'/../Fixtures/legal/terms/synthetic-'.($version === LEGAL_VERSION ? 'v1' : 'v2').'.txt');
}

function legalDigest(string $version = LEGAL_VERSION): string
{
    return hash('sha256', legalFixture($version));
}

function legalDescriptor(string $version = LEGAL_VERSION): array
{
    return [
        'file' => 'synthetic-'.($version === LEGAL_VERSION ? 'v1' : 'v2').'.txt',
        'sha256' => legalDigest($version),
        'published_at' => $version === LEGAL_VERSION ? '2026-01-01T00:00:00Z' : '2026-02-01T00:00:00Z',
    ];
}

function activateLegalGate(): void
{
    config()->set('legal.artifact_directory', __DIR__.'/../Fixtures/legal/terms');
    config()->set('legal.terms', [
        'required_version' => LEGAL_VERSION,
        'published' => [LEGAL_VERSION => legalDescriptor()],
    ]);
}

function fakeGoogleUser(string $email, string $id = 'google-id-1'): Laravel\Socialite\Contracts\User
{
    $user = Mockery::mock(Laravel\Socialite\Contracts\User::class);
    $user->id = $id;
    $user->email = $email;
    $user->name = 'Google User';
    $user->avatar = 'https://example.com/a.png';
    $user->user = ['sub' => $id, 'email' => $email, 'email_verified' => true];

    return $user;
}

// ---------------------------------------------------------------------------
// Gate inactive (default): today's behavior is preserved exactly.
// ---------------------------------------------------------------------------

test('web registration works unchanged while the gate is inactive', function () {
    $this->withoutVite()->get('/register')->assertOk()->assertSee('name="tos_accepted"', false);
    $this->post('/register', [
        'name' => 'Gate Off', 'email' => 'gateoff@example.com', 'password' => 'password',
        'password_confirmation' => 'password', 'tos_accepted' => '1',
    ])->assertRedirect(route('verification.notice'));

    $user = User::where('email', 'gateoff@example.com')->first();
    expect($user)->not->toBeNull();
    expect(LegalAcceptance::query()->count())->toBe(0);
});

test('app api registration needs no terms version while the gate is inactive', function () {
    signedAppRegister([
        'name' => 'App Gate Off', 'email' => 'appgateoff@example.com', 'password' => 'Sup3r-secret!',
        'tos_accepted' => true,
    ])->assertStatus(201)->assertJsonMissingPath('terms');
    expect(LegalAcceptance::query()->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Web registration with the gate active.
// ---------------------------------------------------------------------------

test('web registration records the acceptance with version, source and time', function () {
    activateLegalGate();
    $this->post('/register', [
        'name' => 'Web Taker', 'email' => 'webtaker@example.com', 'password' => 'password',
        'password_confirmation' => 'password', 'tos_accepted' => '1', 'terms_version' => LEGAL_VERSION,
    ])->assertRedirect(route('verification.notice'));

    $user = User::where('email', 'webtaker@example.com')->first();
    $acceptance = LegalAcceptance::where('user_id', $user->id)->first();
    expect($acceptance)->not->toBeNull();
    expect($acceptance->policy)->toBe('terms');
    expect($acceptance->version)->toBe(LEGAL_VERSION);
    expect($acceptance->source)->toBe('web');
    expect($acceptance->accepted_at)->not->toBeNull();
});

test('web registration without the required terms version is refused', function () {
    activateLegalGate();
    $this->post('/register', [
        'name' => 'No Version', 'email' => 'noversion@example.com', 'password' => 'password',
        'password_confirmation' => 'password', 'tos_accepted' => '1',
    ])->assertSessionHasErrors('terms_version');
    expect(User::where('email', 'noversion@example.com')->exists())->toBeFalse();
});

test('a client cannot pick an arbitrary terms version', function () {
    activateLegalGate();
    $this->post('/register', [
        'name' => 'Version Shopper', 'email' => 'shopper@example.com', 'password' => 'password',
        'password_confirmation' => 'password', 'tos_accepted' => '1', 'terms_version' => '1999-01-01.1',
    ])->assertSessionHasErrors('terms_version');
    expect(User::where('email', 'shopper@example.com')->exists())->toBeFalse();
});

test('web registration with the checkbox unticked is refused', function () {
    activateLegalGate();
    $this->post('/register', [
        'name' => 'No Tick', 'email' => 'notick@example.com', 'password' => 'password',
        'password_confirmation' => 'password', 'tos_accepted' => '0', 'terms_version' => LEGAL_VERSION,
    ])->assertSessionHasErrors();
    expect(User::where('email', 'notick@example.com')->exists())->toBeFalse();
});

// ---------------------------------------------------------------------------
// Google OAuth: only the NEW account path enforces consent, server side.
// ---------------------------------------------------------------------------

test('oauth callback creates a new account with consent recorded when the gate is inactive', function () {
    Socialite::shouldReceive('driver')->with('google')->andReturnSelf();
    Socialite::shouldReceive('user')->andReturn(fakeGoogleUser('newoauth@example.com'));

    $this->withSession(['google_oauth_from' => 'register'])
        ->get('/auth/google/callback')
        ->assertRedirect(route('onboarding'));

    $user = User::where('email', 'newoauth@example.com')->first();
    expect($user)->not->toBeNull();
    expect(LegalAcceptance::where('user_id', $user->id)->exists())->toBeFalse();
});

test('oauth callback refuses to create a new account without server-side consent', function () {
    activateLegalGate();
    Socialite::shouldReceive('driver')->with('google')->andReturnSelf();
    Socialite::shouldReceive('user')->andReturn(fakeGoogleUser('noconsent@example.com'));

    $this->withSession(['google_oauth_from' => 'register'])
        ->get('/auth/google/callback')
        ->assertRedirect(route('register'));

    expect(User::where('email', 'noconsent@example.com')->exists())->toBeFalse();
    expect(LegalAcceptance::query()->count())->toBe(0);
    $this->assertGuest();
});

test('oauth new account records the acceptance after explicit server-side consent', function () {
    activateLegalGate();
    // The consent POST is the only thing that may plant the one-shot consent
    // flag; the Google link itself must never create an account.
    $this->post('/auth/google/consent', ['accept' => '1', 'terms_version' => LEGAL_VERSION])
        ->assertRedirect(route('auth.google', ['from_register' => 1]));
    expect(session('legal_oauth_consent.version'))->toBe(LEGAL_VERSION);
    expect(session('legal_oauth_consent.at'))->toBeInt();

    Socialite::shouldReceive('driver')->with('google')->andReturnSelf();
    Socialite::shouldReceive('user')->andReturn(fakeGoogleUser('consented@example.com'));

    $this->withSession(['google_oauth_from' => 'register'])
        ->get('/auth/google/callback')
        ->assertRedirect(route('onboarding'));

    $user = User::where('email', 'consented@example.com')->first();
    expect($user)->not->toBeNull();
    $acceptance = LegalAcceptance::where('user_id', $user->id)->first();
    expect($acceptance)->not->toBeNull();
    expect($acceptance->version)->toBe(LEGAL_VERSION);
    expect($acceptance->source)->toBe('oauth');
    // One-shot: the consent flag is consumed, never reused.
    expect(session('legal_oauth_consent'))->toBeNull();
});

test('oauth consent post with a wrong or missing version does not grant consent', function () {
    activateLegalGate();
    $this->post('/auth/google/consent', [])->assertSessionHasErrors(['accept', 'terms_version']);
    $this->post('/auth/google/consent', ['accept' => '1', 'terms_version' => '1999-01-01.1'])->assertSessionHasErrors('terms_version');
    $this->post('/auth/google/consent', ['accept' => '0', 'terms_version' => LEGAL_VERSION])->assertSessionHasErrors('accept');
    expect(session('legal_oauth_consent'))->toBeNull();
});

test('oauth consent flag expires before it can be used', function () {
    activateLegalGate();
    $this->post('/auth/google/consent', ['accept' => '1', 'terms_version' => LEGAL_VERSION]);
    $this->travel(2)->hours();
    Socialite::shouldReceive('driver')->with('google')->andReturnSelf();
    Socialite::shouldReceive('user')->andReturn(fakeGoogleUser('expired@example.com'));

    $this->withSession(['google_oauth_from' => 'register'])
        ->get('/auth/google/callback')
        ->assertRedirect(route('register'));

    expect(User::where('email', 'expired@example.com')->exists())->toBeFalse();
    expect(LegalAcceptance::query()->count())->toBe(0);
});

test('oauth callback linking an existing email account needs no new consent', function () {
    activateLegalGate();
    // Pre-gate user (accepted nothing): signing in with Google must keep
    // working; no new consent is silently recorded on their behalf.
    $existing = User::create([
        'name' => 'Old Timer', 'email' => 'oldtimer@example.com',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
    ]);
    Socialite::shouldReceive('driver')->with('google')->andReturnSelf();
    Socialite::shouldReceive('user')->andReturn(fakeGoogleUser('oldtimer@example.com'));

    $this->withSession(['google_oauth_from' => 'login'])
        ->get('/auth/google/callback')
        ->assertRedirect();

    expect($existing->fresh()->google_id)->toBe('google-id-1');
    expect($this->app['auth']->check())->toBeTrue();
    expect(LegalAcceptance::where('user_id', $existing->id)->exists())->toBeFalse();
});

// ---------------------------------------------------------------------------
// App-delegated registration (signed server-to-server) with the gate active.
// ---------------------------------------------------------------------------

test('app api registration records the acceptance from the signed body', function () {
    activateLegalGate();
    signedAppRegister([
        'name' => 'App Taker', 'email' => 'apptaker@example.com', 'password' => 'Sup3r-secret!',
        'tos_accepted' => true, 'terms_version' => LEGAL_VERSION,
    ])->assertStatus(201)
        ->assertJsonPath('terms.version', LEGAL_VERSION)
        ->assertJsonPath('terms.source', 'app');

    $user = User::where('email', 'apptaker@example.com')->first();
    $acceptance = LegalAcceptance::where('user_id', $user->id)->first();
    expect($acceptance)->not->toBeNull();
    expect($acceptance->version)->toBe(LEGAL_VERSION);
    expect($acceptance->source)->toBe('app');
});

test('app api registration without the required terms version is refused', function () {
    activateLegalGate();
    signedAppRegister([
        'name' => 'App No Version', 'email' => 'appnoversion@example.com', 'password' => 'Sup3r-secret!',
        'tos_accepted' => true,
    ])->assertStatus(422);
    expect(User::where('email', 'appnoversion@example.com')->exists())->toBeFalse();
});

test('app api registration cannot pick an arbitrary terms version', function () {
    activateLegalGate();
    signedAppRegister([
        'name' => 'App Shopper', 'email' => 'appshopper@example.com', 'password' => 'Sup3r-secret!',
        'tos_accepted' => true, 'terms_version' => '1999-01-01.1',
    ])->assertStatus(422);
    expect(User::where('email', 'appshopper@example.com')->exists())->toBeFalse();
});

test('oauth consent cannot be replayed after it is consumed', function () {
    activateLegalGate();
    $this->withSession([
        'google_oauth_from' => 'register',
        'legal_oauth_consent' => ['version' => LEGAL_VERSION, 'at' => now()->getTimestamp()],
    ]);
    Socialite::shouldReceive('driver')->with('google')->twice()->andReturnSelf();
    Socialite::shouldReceive('user')->twice()->andReturn(fakeGoogleUser('replay@example.com'));

    $this->get('/auth/google/callback')->assertRedirect(route('onboarding'));
    $this->app['auth']->logout();
    $this->withSession(['google_oauth_from' => 'register'])
        ->get('/auth/google/callback')->assertRedirect(route('login'));

    expect(User::where('email', 'replay@example.com')->count())->toBe(1);
});

test('oauth consent is rejected when the required version changes', function () {
    activateLegalGate();
    config()->set('legal.terms', [
        'required_version' => '2026-02-01.1',
        'published' => [
            LEGAL_VERSION => legalDescriptor(),
            '2026-02-01.1' => legalDescriptor('2026-02-01.1'),
        ],
    ]);
    $this->withSession([
        'google_oauth_from' => 'register',
        'legal_oauth_consent' => ['version' => LEGAL_VERSION, 'at' => now()->getTimestamp()],
    ]);
    Socialite::shouldReceive('driver')->with('google')->andReturnSelf();
    Socialite::shouldReceive('user')->andReturn(fakeGoogleUser('changed@example.com'));

    $this->get('/auth/google/callback')->assertRedirect(route('register'));
    expect(User::where('email', 'changed@example.com')->exists())->toBeFalse();
});

test('active unpublished configuration fails closed on every creation path', function () {
    config()->set('legal.terms.required_version', LEGAL_VERSION);
    $this->withoutVite()->get('/register')->assertServerError();
    signedAppRegister([
        'name' => 'No Downgrade', 'email' => 'nodowngrade@example.com', 'password' => 'Sup3r-secret!',
        'tos_accepted' => true,
    ])->assertStatus(503)->assertJsonPath('error', 'not_configured');

    Socialite::shouldReceive('driver')->with('google')->andReturnSelf();
    Socialite::shouldReceive('user')->andReturn(fakeGoogleUser('oauthdowngrade@example.com'));
    $this->withSession(['google_oauth_from' => 'register'])
        ->get('/auth/google/callback')->assertRedirect(route('login'));

    expect(User::whereIn('email', ['nodowngrade@example.com', 'oauthdowngrade@example.com'])->exists())->toBeFalse();
});

test('web registration rolls back the user and does not log in when acceptance fails', function () {
    activateLegalGate();
    Mail::fake();
    LegalAcceptance::creating(fn () => throw new RuntimeException('Synthetic acceptance failure'));

    $this->from('/register')->post('/register', [
        'name' => 'Rollback Web', 'email' => 'rollbackweb@example.com', 'password' => 'password',
        'password_confirmation' => 'password', 'tos_accepted' => '1', 'terms_version' => LEGAL_VERSION,
    ])->assertServerError();

    expect(User::where('email', 'rollbackweb@example.com')->exists())->toBeFalse();
    expect(LegalAcceptance::query()->count())->toBe(0);
    $this->assertGuest();
    Mail::assertNothingSent();
});

test('oauth registration rolls back before notifications and login when acceptance fails', function () {
    activateLegalGate();
    Mail::fake();
    $notifications = Mockery::mock(NotificationService::class);
    $notifications->shouldNotReceive('notifyNewUser');
    $this->app->instance(NotificationService::class, $notifications);
    LegalAcceptance::creating(fn () => throw new RuntimeException('Synthetic acceptance failure'));
    Socialite::shouldReceive('driver')->with('google')->andReturnSelf();
    Socialite::shouldReceive('user')->andReturn(fakeGoogleUser('rollbackoauth@example.com'));

    $this->withSession([
        'google_oauth_from' => 'register',
        'legal_oauth_consent' => ['version' => LEGAL_VERSION, 'at' => now()->getTimestamp()],
    ])->get('/auth/google/callback')->assertRedirect(route('login'));

    expect(User::where('email', 'rollbackoauth@example.com')->exists())->toBeFalse();
    expect(LegalAcceptance::query()->count())->toBe(0);
    $this->assertGuest();
    Mail::assertNothingSent();
});

test('app registration rolls back the user when acceptance fails', function () {
    activateLegalGate();
    Mail::fake();
    LegalAcceptance::creating(fn () => throw new RuntimeException('Synthetic acceptance failure'));

    signedAppRegister([
        'name' => 'Rollback App', 'email' => 'rollbackapp@example.com', 'password' => 'Sup3r-secret!',
        'tos_accepted' => true, 'terms_version' => LEGAL_VERSION,
    ])->assertServerError();

    expect(User::where('email', 'rollbackapp@example.com')->exists())->toBeFalse();
    expect(LegalAcceptance::query()->count())->toBe(0);
    Mail::assertNothingSent();
});

// ---------------------------------------------------------------------------
// Model + migration behavior.
// ---------------------------------------------------------------------------

test('published artifacts are served by exact version with their registered bytes', function () {
    activateLegalGate();
    $url = route('legal.terms.artifact', ['version' => LEGAL_VERSION]);
    expect($url)->toBe(route('legal.terms.artifact', ['version' => LEGAL_VERSION]));
    $this->get($url)->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertHeader('Cache-Control', 'immutable, max-age=31536000, public')
        ->assertSee('SYNTHETIC TEST FIXTURE ONLY', false)
        ->assertSee('2026-01-01.1');
    $this->get('/legal/terms/not-registered')->assertNotFound();
});

test('signup and oauth consent link the exact version artifact and retain its digest', function () {
    activateLegalGate();
    $this->withoutVite()->get('/register')->assertOk()
        ->assertSee('/legal/terms/'.LEGAL_VERSION, false)
        ->assertSee('Terms of Service (version '.LEGAL_VERSION.')');
    $this->get('/auth/google/consent')->assertOk()->assertSee('/legal/terms/'.LEGAL_VERSION, false);
    $this->post('/register', [
        'name' => 'Digest Bound', 'email' => 'digestbound@example.com', 'password' => 'password',
        'password_confirmation' => 'password', 'tos_accepted' => '1', 'terms_version' => LEGAL_VERSION,
    ]);
    expect(LegalAcceptance::first()->sha256)->toBe(legalDigest());
});

test('artifact content or descriptor drift fails closed and preserves old acceptance evidence', function () {
    activateLegalGate();
    $user = User::factory()->create();
    $oldDigest = legalDigest();
    $old = Legal::record($user, 'web', LEGAL_VERSION);
    $fixture = __DIR__.'/../Fixtures/legal/terms/synthetic-v1.txt';
    $original = file_get_contents($fixture);
    file_put_contents($fixture, $original.'tampered');
    try {
        $this->get('/legal/terms/'.LEGAL_VERSION)->assertStatus(503);
        $this->get('/api/legal/terms')->assertStatus(503);
        expect(Legal::isUsable(LEGAL_VERSION))->toBeFalse();
        $this->post('/register', [
            'name' => 'Tampered', 'email' => 'tampered@example.com', 'password' => 'password',
            'password_confirmation' => 'password', 'tos_accepted' => '1', 'terms_version' => LEGAL_VERSION,
        ])->assertServerError();
        expect(User::where('email', 'tampered@example.com')->exists())->toBeFalse();
        expect($old->fresh()->sha256)->toBe($oldDigest);
    } finally {
        file_put_contents($fixture, $original);
    }
});

test('unsafe paths and missing or future publication metadata fail closed', function (array $descriptor) {
    activateLegalGate();
    config()->set('legal.terms.published', [LEGAL_VERSION => $descriptor]);
    $this->get('/legal/terms/'.LEGAL_VERSION)->assertStatus(503);
    $this->get('/api/legal/terms')->assertStatus(503);
    expect(Legal::isPublished(LEGAL_VERSION))->toBeFalse();
})->with([
    'traversal' => [array_merge(legalDescriptor(), ['file' => '../synthetic-v1.txt'])],
    'remote url' => [array_merge(legalDescriptor(), ['file' => 'https://example.com/terms.txt'])],
    'wrong digest' => [array_merge(legalDescriptor(), ['sha256' => str_repeat('0', 64)])],
    'malformed digest' => [array_merge(legalDescriptor(), ['sha256' => strtoupper(legalDigest())])],
    'missing time' => [array_merge(legalDescriptor(), ['published_at' => null])],
    'future time' => [array_merge(legalDescriptor(), ['published_at' => now()->addDay()->toIso8601String()])],
]);

test('consumer metadata is public no-store and rotates to a new content digest', function () {
    $this->get('/api/legal/terms')->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertExactJson(['active' => false, 'terms' => null]);
    activateLegalGate();
    $first = $this->get('/api/legal/terms')->assertOk()
        ->assertJsonPath('active', true)
        ->assertJsonPath('terms.version', LEGAL_VERSION)
        ->assertJsonPath('terms.sha256', legalDigest())
        ->assertJsonPath('terms.published_at', '2026-01-01T00:00:00Z')
        ->assertJsonPath('terms.url', '/legal/terms/'.LEGAL_VERSION);
    expect($first->json('terms'))->not->toHaveKeys(['contents', 'user', 'email', 'secret']);
    config()->set('legal.terms', [
        'required_version' => '2026-02-01.1',
        'published' => ['2026-02-01.1' => legalDescriptor('2026-02-01.1')],
    ]);
    $this->get('/api/legal/terms')->assertOk()
        ->assertJsonPath('terms.version', '2026-02-01.1')
        ->assertJsonPath('terms.sha256', legalDigest('2026-02-01.1'));
    $this->get('/legal/terms/'.LEGAL_VERSION)->assertNotFound();
});

test('app validation response contains structured current artifact metadata', function () {
    activateLegalGate();
    signedAppRegister([
        'name' => 'Metadata Client', 'email' => 'metadataclient@example.com', 'password' => 'Sup3r-secret!',
        'tos_accepted' => true,
    ])->assertStatus(422)->assertJsonPath('error', 'invalid')
        ->assertJsonPath('legal.terms.version', LEGAL_VERSION)
        ->assertJsonPath('legal.terms.sha256', legalDigest());
});

test('migration refuses existing or drifted tables and down is non-destructive', function () {
    $migration = require database_path('migrations/2026_10_30_000001_create_legal_acceptances_table.php');
    $before = Schema::getColumnListing('legal_acceptances');
    expect(fn () => $migration->up())->toThrow(RuntimeException::class);
    expect(Schema::getColumnListing('legal_acceptances'))->toBe($before);
    Schema::table('legal_acceptances', function (Blueprint $table) {
        $table->dropColumn('sha256');
    });
    expect(fn () => $migration->up())->toThrow(RuntimeException::class);
    expect(Schema::hasColumn('legal_acceptances', 'sha256'))->toBeFalse();
    $migration->down();
    expect(Schema::hasTable('legal_acceptances'))->toBeTrue();
});

test('existing acceptance cannot be silently rebound to a new digest', function () {
    $user = User::factory()->create();
    $row = LegalAcceptance::record($user, 'terms', LEGAL_VERSION, 'web', legalDigest());
    expect(fn () => LegalAcceptance::record($user, 'terms', LEGAL_VERSION, 'oauth', legalDigest('2026-02-01.1')))
        ->toThrow(LogicException::class);
    expect($row->fresh()->sha256)->toBe(legalDigest());
});

test('legal acceptances table exists with its unique constraint', function () {
    expect(Schema::hasTable('legal_acceptances'))->toBeTrue();
    $user = User::factory()->create();
    $first = LegalAcceptance::record($user, 'terms', LEGAL_VERSION, 'web', legalDigest());
    expect($first)->not->toBeNull();
    // Same triple again is idempotent: one row, no exception.
    $again = LegalAcceptance::record($user, 'terms', LEGAL_VERSION, 'oauth', legalDigest());
    expect($again->id)->toBe($first->id);
    expect(LegalAcceptance::where('user_id', $user->id)->count())->toBe(1);
});

test('acceptances cascade away with the user', function () {
    $user = User::factory()->create();
    LegalAcceptance::record($user, 'terms', LEGAL_VERSION, 'web', legalDigest());
    $user->delete();
    expect(LegalAcceptance::count())->toBe(0);
});
