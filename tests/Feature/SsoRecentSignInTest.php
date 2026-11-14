<?php

use App\Models\HopApp;
use App\Models\User;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Tests\TestCase;

const RECENT_STATE = 'state-state-state-1234';

function recentUrl(array $extra = [], string $app = 'filehop'): string
{
    return '/sso/'.$app.'?'.http_build_query(['state' => RECENT_STATE, 'return_to' => 'https://file.example.com/auth/sso/callback'] + $extra);
}

/** Laravel keeps the full URL with its query keys sorted. */
function recentIntended(string $url): string
{
    parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
    ksort($q);

    return 'http://localhost'.parse_url($url, PHP_URL_PATH).'?'.http_build_query($q);
}

/** The "remember me" cookie of $user as the browser would send it (encrypted like Laravel does). */
function recentRememberCookie(User $user, string $token): array
{
    $name = Auth::guard('web')->getRecallerName();
    $enc = app('encrypter');
    $value = $user->id.'|'.$token.'|'.$user->getAuthPassword();

    return [$name, $enc->encrypt(CookieValuePrefix::create($name, $enc->getKey()).$value, false)];
}

function recentTicket(string $location): array
{
    parse_str((string) parse_url($location, PHP_URL_QUERY), $q);
    [$body] = explode('.', $q['ticket']);

    return json_decode(base64_decode(strtr($body, '-_', '+/')), true);
}

/** A hub session of $user whose last real sign-in was $ago seconds back (null: no stamp at all). */
function recentSession(User $user, ?int $ago): TestCase
{
    test()->flushSession();
    $session = ['2fa_verified' => true];
    if ($ago !== null) {
        $session['auth_time'] = time() - $ago;
    }

    return test()->actingAs($user)->withSession($session);
}

beforeEach(function () {
    HopApp::factory()->filehop()->create();
});

// ---- the stamp ------------------------------------------------------------------------

test('the ticket carries auth_time, and 0 when the session has none', function () {
    $user = User::factory()->create();
    $stamp = time() - 40;
    $res = test()->actingAs($user)->withSession(['2fa_verified' => true, 'auth_time' => $stamp])->get(recentUrl());
    expect(recentTicket($res->headers->get('Location')))->toMatchArray(['auth_time' => $stamp, 'v' => 2, 'aud' => 'filehop']);

    $res = recentSession(User::factory()->create(), null)->get(recentUrl());
    expect(recentTicket($res->headers->get('Location'))['auth_time'])->toBe(0);
});

test('signing in with the password stamps auth_time', function () {
    $user = User::factory()->create();
    $before = time();
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();
    expect(session('auth_time'))->toBeGreaterThanOrEqual($before)->toBeLessThanOrEqual(time());
});

test('with two-factor the stamp is set when the code is accepted, not at the password step', function () {
    $user = User::factory()->create(['two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'), 'two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => encrypt(json_encode(['ABCDE-FGHIJ']))]);
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();
    expect(session('auth_time'))->toBeNull();
    $this->post('/two-factor-challenge', ['code' => 'abcde-fghij'])->assertRedirect();
    expect(session('2fa_verified'))->toBeTrue()->and(session('auth_time'))->toBeGreaterThan(time() - 30);
});

test('a new sign-up stamps auth_time', function () {
    $this->post('/register', ['name' => 'Helena', 'email' => 'helena@example.com', 'password' => 'a-long-password-9!', 'password_confirmation' => 'a-long-password-9!', 'tos_accepted' => '1'])->assertRedirect();
    $this->assertAuthenticated();
    expect(session('auth_time'))->toBeGreaterThan(time() - 30);
});

test('signing in with Google stamps auth_time', function () {
    $user = User::factory()->create(['email' => 'helena@example.com', 'google_id' => 'G-HEL']);
    $g = Mockery::mock(Laravel\Socialite\Contracts\User::class);
    $g->id = 'G-HEL';
    $g->email = 'helena@example.com';
    $g->name = 'Helena';
    $g->avatar = 'https://example.com/a.png';
    $g->user = ['email_verified' => true];
    Socialite::shouldReceive('driver')->with('google')->andReturnSelf();
    Socialite::shouldReceive('user')->andReturn($g);
    $this->get('/auth/google/callback')->assertRedirect();
    $this->assertAuthenticatedAs($user);
    expect(session('auth_time'))->toBeGreaterThan(time() - 30);
});

test('the hand-over to an app does not move auth_time', function () {
    $stamp = time() - 1000;
    recentSession(User::factory()->create(), 1000)->get(recentUrl())->assertRedirectContains('ticket=');
    expect(session('auth_time'))->toBeLessThanOrEqual($stamp + 2);
});

test('a "remember me" cookie that restores the session is not a sign-in: auth_time stays unknown', function () {
    $user = User::factory()->create(['remember_token' => 'remember-token-0123456789']);
    [$name, $cookie] = recentRememberCookie($user, 'remember-token-0123456789');
    $res = test()->withUnencryptedCookie($name, $cookie)->get(recentUrl(['max_age' => 300]));
    // Restored by the cookie, but no proof of a sign-in: the person is asked again.
    $res->assertRedirect(route('login'));
    expect(session()->has(Auth::guard('web')->getName()))->toBeFalse();
    $res->assertCookieExpired($name);
});

// ---- max_age --------------------------------------------------------------------------

test('without max_age nothing changes, however old the sign-in is', function () {
    foreach ([null, 100000] as $ago) {
        $res = recentSession(User::factory()->create(), $ago)->get(recentUrl());
        expect($res->headers->get('Location'))->toStartWith('https://file.example.com/auth/sso/callback?ticket=');
    }
});

test('max_age with a recent sign-in gives the ticket at once', function () {
    $res = recentSession(User::factory()->create(), 100)->get(recentUrl(['max_age' => 300]));
    expect($res->headers->get('Location'))->toStartWith('https://file.example.com/auth/sso/callback?ticket=');
    expect(recentTicket($res->headers->get('Location'))['auth_time'])->toBeGreaterThan(time() - 110);
});

test('max_age with an older sign-in ends this hub session and shows the sign-in page, then returns to the same step', function () {
    $user = User::factory()->create(['remember_token' => 'remember-token-0123456789']);
    $url = recentUrl(['max_age' => 300]);
    $res = recentSession($user, 301)->get($url);
    $res->assertRedirect(route('login'));
    $this->assertGuest();
    expect(session('url.intended'))->toBe(recentIntended($url))
        ->and(session('status'))->toBe('Please sign in again to confirm it is you.')
        ->and(session('auth_time'))->toBeNull();
    // Other devices: the remember token is not rotated.
    expect($user->fresh()->remember_token)->toBe('remember-token-0123456789');

    // Signing in again is enough: the ticket follows, with a fresh auth_time and without another loop.
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(recentIntended($url));
    $res = $this->get($url);
    expect($res->headers->get('Location'))->toStartWith('https://file.example.com/auth/sso/callback?ticket=');
    expect(recentTicket($res->headers->get('Location'))['auth_time'])->toBeGreaterThan(time() - 10);
});

test('a session without any stamp counts as old', function () {
    recentSession(User::factory()->create(), null)->get(recentUrl(['max_age' => 300]))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('a guest who signs in on the way gets the ticket without a second sign-in', function () {
    $user = User::factory()->create();
    $url = recentUrl(['max_age' => 300]);
    $this->get($url)->assertRedirect(route('login'));
    expect(session('url.intended'))->toBe(recentIntended($url));
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(recentIntended($url));
    expect($this->get($url)->headers->get('Location'))->toContain('?ticket=');
});

test('prompt=login is max_age=60', function () {
    recentSession(User::factory()->create(), 61)->get(recentUrl(['prompt' => 'login']))->assertRedirect(route('login'));
    $this->assertGuest();
    $res = recentSession(User::factory()->create(), 30)->get(recentUrl(['prompt' => 'login']));
    expect($res->headers->get('Location'))->toContain('?ticket=');
    // The smaller of the two wins.
    recentSession(User::factory()->create(), 100)->get(recentUrl(['prompt' => 'login', 'max_age' => 600]))->assertRedirect(route('login'));
});

test('prompt=none with an old sign-in answers login_required and shows no page', function () {
    $user = User::factory()->create(['remember_token' => 'remember-token-0123456789']);
    $res = recentSession($user, 5000)->get(recentUrl(['prompt' => 'none', 'max_age' => 300]));
    expect($res->headers->get('Location'))->toBe('https://file.example.com/auth/sso/callback?error=login_required&state='.RECENT_STATE);
    // Nothing is ended or remembered by a silent check.
    expect(session('url.intended'))->toBeNull();
    $this->assertAuthenticated();
    $res = recentSession($user, 10)->get(recentUrl(['prompt' => 'none', 'max_age' => 300]));
    expect($res->headers->get('Location'))->toContain('?ticket=');
});

test('a max_age that is not plain digits is ignored, a huge one is cut to a day, a tiny one to ten seconds', function () {
    foreach (['abc', '-5', '1e9', '30.5', '', '0x10', '3 0'] as $bad) {
        $res = recentSession(User::factory()->create(), 100000)->get(recentUrl(['max_age' => $bad]));
        expect($res->headers->get('Location'))->toContain('?ticket=');
    }
    $res = recentSession(User::factory()->create(), 100000)->get('/sso/filehop?state='.RECENT_STATE.'&return_to='.urlencode('https://file.example.com/auth/sso/callback').'&max_age[]=1');
    expect($res->headers->get('Location'))->toContain('?ticket=');
    // Above a day: cut to 86400.
    recentSession(User::factory()->create(), 86401)->get(recentUrl(['max_age' => '99999999']))->assertRedirect(route('login'));
    $res = recentSession(User::factory()->create(), 80000)->get(recentUrl(['max_age' => '99999999']));
    expect($res->headers->get('Location'))->toContain('?ticket=');
    // Below ten seconds: raised to ten, so the sign-in just done always passes.
    $res = recentSession(User::factory()->create(), 3)->get(recentUrl(['max_age' => '0']));
    expect($res->headers->get('Location'))->toContain('?ticket=');
});

test('a return_to that is not the registered callback stays a 400, with max_age too', function () {
    recentSession(User::factory()->create(), 5000)->get('/sso/filehop?'.http_build_query(['state' => RECENT_STATE, 'max_age' => 300, 'return_to' => 'https://evil.example/cb']))->assertStatus(400);
    $this->assertAuthenticated();
});

test('an open two-factor check still goes to the code page, and the sign-in step is kept', function () {
    $user = User::factory()->create(['two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'), 'two_factor_confirmed_at' => now()]);
    $url = recentUrl(['max_age' => 300]);
    test()->actingAs($user)->get($url)->assertRedirect(route('two-factor.show'));
    expect(session('url.intended'))->toBe(recentIntended($url));
});

test('a suspended account and an unverified address are handled as before', function () {
    $suspended = User::factory()->create(['status' => User::STATUS_SUSPENDED]);
    recentSession($suspended, 5000)->get(recentUrl(['max_age' => 300]))->assertRedirect(route('suspended'));
    $unverified = User::factory()->unverified()->create();
    recentSession($unverified, 5000)->get(recentUrl(['max_age' => 300]))->assertRedirect(route('verification.notice'));
});

test('the Google button passes max_age and prompt on to the ticket step', function () {
    config()->set('services.google.client_id', 'x');
    test()->flushSession();
    $res = test()->get('/sso/filehop/google?'.http_build_query(['state' => RECENT_STATE, 'return_to' => 'https://file.example.com/auth/sso/callback', 'max_age' => 120, 'prompt' => 'login']));
    $res->assertRedirect();
    parse_str((string) parse_url(session('url.intended'), PHP_URL_QUERY), $q);
    expect($q)->toMatchArray(['max_age' => '120', 'prompt' => 'login', 'state' => RECENT_STATE]);
    // Without them nothing extra is added.
    test()->flushSession();
    test()->get('/sso/filehop/google?'.http_build_query(['state' => RECENT_STATE, 'return_to' => 'https://file.example.com/auth/sso/callback']));
    expect(session('url.intended'))->not->toContain('max_age')->not->toContain('prompt');
    // Signed in with an old sign-in: the Google button sends on to the ticket step, which asks again.
    $res = recentSession(User::factory()->create(), 5000)->get('/sso/filehop/google?'.http_build_query(['state' => RECENT_STATE, 'return_to' => 'https://file.example.com/auth/sso/callback', 'max_age' => 120]));
    expect($res->headers->get('Location'))->toContain('max_age=120');
});

// ---- review round ---------------------------------------------------------------------

test('a member who also carries a "remember me" cookie really gets the sign-in page, and the cookie of other devices stays valid', function () {
    $user = User::factory()->create(['remember_token' => 'remember-token-0123456789']);
    [$name, $cookie] = recentRememberCookie($user, 'remember-token-0123456789');
    $res = test()->withUnencryptedCookie($name, $cookie)
        ->withSession(['2fa_verified' => true, 'auth_time' => time() - 5000])
        ->get(recentUrl(['max_age' => 300]));
    $res->assertRedirect(route('login'));
    // The session of this request must not hold the member again (the cookie must not be read a second time).
    expect(session()->has(Auth::guard('web')->getName()))->toBeFalse();
    $res->assertCookieExpired($name);
    expect($user->fresh()->remember_token)->toBe('remember-token-0123456789');
});

test('a session restored by "remember me" gets no fresh auth_time from a two-factor code alone', function () {
    $user = User::factory()->create(['two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'), 'two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => encrypt(json_encode(['ABCDE-FGHIJ']))]);
    test()->actingAs($user)->post('/two-factor-challenge', ['code' => 'abcde-fghij'])->assertRedirect();
    expect(session('2fa_verified'))->toBeTrue()->and(session('auth_time'))->toBeNull();
});

test('after a real password sign-in the code stamps, once', function () {
    $user = User::factory()->create(['two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'), 'two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => encrypt(json_encode(['ABCDE-FGHIJ', 'KLMNO-PQRST']))]);
    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $this->post('/two-factor-challenge', ['code' => 'abcde-fghij']);
    expect(session('auth_time'))->toBeGreaterThan(time() - 30);
    // A later code in the same session does not move it.
    session()->put('auth_time', 1000);
    $this->post('/two-factor-challenge', ['code' => 'klmno-pqrst']);
    expect(session('auth_time'))->toBe(1000);
});

test('Google with two-factor stamps after the code, not before', function () {
    $user = User::factory()->create(['email' => 'helena@example.com', 'google_id' => 'G-HEL', 'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'), 'two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => encrypt(json_encode(['ABCDE-FGHIJ']))]);
    $g = Mockery::mock(Laravel\Socialite\Contracts\User::class);
    $g->id = 'G-HEL';
    $g->email = 'helena@example.com';
    $g->name = 'Helena';
    $g->avatar = 'https://example.com/a.png';
    $g->user = ['email_verified' => true];
    Socialite::shouldReceive('driver')->with('google')->andReturnSelf();
    Socialite::shouldReceive('user')->andReturn($g);
    $this->get('/auth/google/callback')->assertRedirect();
    expect(session('auth_time'))->toBeNull();
    $this->post('/two-factor-challenge', ['code' => 'abcde-fghij'])->assertRedirect();
    expect(session('auth_time'))->toBeGreaterThan(time() - 30);
});

test('a max_age of ten digits or more is cut to a day, not ignored', function () {
    foreach (['1234567890', '99999999999999999999999'] as $big) {
        recentSession(User::factory()->create(), 86401)->get(recentUrl(['max_age' => $big]))->assertRedirect(route('login'));
        $res = recentSession(User::factory()->create(), 80000)->get(recentUrl(['max_age' => $big]));
        expect($res->headers->get('Location'))->toContain('?ticket=');
    }
});
