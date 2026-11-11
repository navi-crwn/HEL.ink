<?php

use App\Models\HopApp;
use App\Models\User;
use App\Support\EmailDomains;
use App\Support\HopSignature;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Laravel\Socialite\Facades\Socialite;

/*
 * Throwaway e-mail domains: the union of the committed snapshot of the named list, its server copy, the package's list
 * and the owner's own entries, with the allowlist winning; applied where an address is set, never at sign-in.
 */

const BLOCKLIST_APP_SECRET = 'synthetic-pixelhop-secret-that-is-long-enough';

/** A domain that is in the committed snapshot of the named list. */
function listedDomain(): string
{
    $domains = EmailDomains::parse(file_get_contents(EmailDomains::snapshotPath()));

    return $domains[(int) floor(count($domains) / 2)];
}

function blocklistRegisterViaApp(string $email): TestResponse
{
    if (! HopApp::where('slug', 'pixelhop')->exists()) {
        HopApp::factory()->pixelhop(BLOCKLIST_APP_SECRET)->create(['capabilities' => ['account_api']]);
    }
    $body = json_encode(['name' => 'App Person', 'email' => $email, 'password' => 'Sturdy-pass-12345', 'tos_accepted' => true], JSON_THROW_ON_ERROR);
    $timestamp = (string) time();
    $signature = HopSignature::b64(hash_hmac('sha256', 'pixelhop-api|'.$timestamp.'|'.$body, BLOCKLIST_APP_SECRET, true));

    return test()->call('POST', '/api/pixelhop/register', [], [], [], test()->transformHeadersToServerVars([
        'Content-Type' => 'application/json', 'Accept' => 'application/json',
        'X-PixelHop-Timestamp' => $timestamp, 'X-PixelHop-Signature' => $signature,
    ]), $body);
}

beforeEach(function () {
    EmailDomains::forget();
    @unlink(EmailDomains::refreshedPath());
    Queue::fake();
});

afterEach(function () {
    @unlink(EmailDomains::refreshedPath());
    EmailDomains::forget();
});

test('the committed snapshot is a well-formed list of domains with its notice', function () {
    $lines = preg_split('/\R/', trim(file_get_contents(EmailDomains::snapshotPath())));
    $domains = 0;
    foreach ($lines as $line) {
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        expect(EmailDomains::isDomain($line))->toBeTrue("not a domain: {$line}")
            ->and($line)->toBe(strtolower($line));
        $domains++;
    }
    expect($domains)->toBeGreaterThan(5000);
    $notice = file_get_contents(base_path('resources/data/disposable_email_blocklist.NOTICE.md'));
    expect($notice)->toContain('disposable-email-domains/disposable-email-domains')->toContain('CC0 1.0')->toMatch('/commit `[0-9a-f]{40}`/');
});

test('matching is case-insensitive, covers parent domains and compares internationalised domains in ASCII', function () {
    $d = listedDomain();
    expect(EmailDomains::blocked('someone@'.$d))->toBeTrue()
        ->and(EmailDomains::blocked('Someone@'.strtoupper($d)))->toBeTrue()
        ->and(EmailDomains::blocked('someone@mail.sub.'.$d))->toBeTrue()
        ->and(EmailDomains::blocked('someone@'.$d.'.'))->toBeTrue()
        ->and(EmailDomains::blocked('someone@gmail.com'))->toBeFalse()
        ->and(EmailDomains::blocked('someone@example.com'))->toBeFalse()
        ->and(EmailDomains::blocked('not-an-address'))->toBeFalse();

    config()->set('hop.email.block', ['xn--mnchen-3ya.de']);
    expect(EmailDomains::blocked('a@münchen.de'))->toBeTrue()->and(EmailDomains::blocked('a@MÜNCHEN.de'))->toBeTrue()->and(EmailDomains::blocked('a@sub.münchen.de'))->toBeTrue();
});

test('the allowlist wins over every list and the owner\'s own blocklist adds domains', function () {
    $d = listedDomain();
    config()->set('hop.email.allow', [$d]);
    expect(EmailDomains::blocked('a@'.$d))->toBeFalse()->and(EmailDomains::blocked('a@sub.'.$d))->toBeFalse();

    config()->set('hop.email.allow', []);
    config()->set('hop.email.block', ['spammy-example.test']);
    expect(EmailDomains::blocked('a@spammy-example.test'))->toBeTrue()->and(EmailDomains::blocked('a@x.spammy-example.test'))->toBeTrue();
});

test('the package\'s own list still counts', function () {
    $packageDomains = json_decode(file_get_contents(base_path('vendor/propaganistas/laravel-disposable-email/domains.json')), true);
    $only = null;
    $snapshot = array_flip(EmailDomains::parse(file_get_contents(EmailDomains::snapshotPath())));
    foreach ($packageDomains as $dom) {
        if (! isset($snapshot[$dom])) {
            $only = $dom;
            break;
        }
    }
    expect($only)->not->toBeNull();
    expect(EmailDomains::blocked('a@'.$only))->toBeTrue();
});

test('sign-up on the web refuses a listed domain and a subdomain of one, and accepts an ordinary address', function () {
    $this->withoutVite();
    $base = ['name' => 'Some One', 'password' => 'Sturdy-pass-12345', 'password_confirmation' => 'Sturdy-pass-12345', 'tos_accepted' => '1'];
    Http::fake();

    foreach (['one@'.listedDomain(), 'one@deep.sub.'.listedDomain()] as $email) {
        $this->post('/register', $base + ['email' => $email])->assertSessionHasErrors(['email' => 'This email address cannot be used. Please use another address.']);
    }
    expect(User::count())->toBe(0);

    $this->post('/register', $base + ['email' => 'ordinary@example.com'])->assertSessionHasNoErrors();
    expect(User::where('email', 'ordinary@example.com')->exists())->toBeTrue();
    Http::assertNothingSent(); // the list is read from files, never from the network
});

test('the refusal is in the language of the page and does not name a list', function () {
    $this->withoutVite();
    $base = ['name' => 'Some One', 'password' => 'Sturdy-pass-12345', 'password_confirmation' => 'Sturdy-pass-12345', 'tos_accepted' => '1'];

    $res = $this->post('/register?lang=de', $base + ['email' => 'one@'.listedDomain()]);
    $res->assertSessionHasErrors(['email' => 'Diese E-Mail-Adresse kann nicht verwendet werden. Bitte nutze eine andere Adresse.']);
    expect(json_encode(session('errors')->getBag('default')->all()))->not->toContain('disposable')->not->toContain('list');
});

test('the apps\' sign-up refuses a listed domain and accepts an ordinary one', function () {
    blocklistRegisterViaApp('app@'.listedDomain())->assertStatus(422);
    expect(User::where('email', 'app@'.listedDomain())->exists())->toBeFalse();

    blocklistRegisterViaApp('app.person@example.com')->assertSuccessful();
    expect(User::where('email', 'app.person@example.com')->exists())->toBeTrue();
});

test('changing the e-mail to a throwaway address is refused, while saving an account that already has one is not', function () {
    $user = User::factory()->create(['email' => 'real@example.com', 'username' => 'switcher', 'password' => Hash::make('right-password-1')]);
    $form = ['name' => $user->name, 'username' => 'switcher'];

    $this->actingAs($user)->withSession(['2fa_verified' => true])
        ->patch('/profile', $form + ['email' => 'burner@'.listedDomain(), 'current_password' => 'right-password-1'])
        ->assertSessionHasErrors(['email' => 'This email address cannot be used. Please use another address.']);
    $this->actingAs($user)->withSession(['2fa_verified' => true])
        ->patch('/profile', $form + ['email' => 'burner@sub.'.listedDomain(), 'current_password' => 'right-password-1'])
        ->assertSessionHasErrors('email');
    expect($user->fresh()->email)->toBe('real@example.com');

    // An account created before its domain was listed keeps working: nothing is enforced at sign-in or on an unchanged address.
    $old = User::factory()->create(['email' => 'legacy@'.listedDomain(), 'username' => 'legacyuser']);
    $this->actingAs($old)->withSession(['2fa_verified' => true])
        ->patch('/profile', ['name' => 'Renamed', 'username' => 'legacyuser', 'email' => 'legacy@'.listedDomain()])->assertSessionHasNoErrors();
    expect($old->fresh()->name)->toBe('Renamed');
    $this->post('/logout');
    $this->post('/login', ['email' => 'legacy@'.listedDomain(), 'password' => 'password']);
    $this->assertAuthenticated();
});

test('a new Google sign-up on a throwaway domain is refused, an existing account on it is not touched', function () {
    $d = listedDomain();
    $g = Mockery::mock(Laravel\Socialite\Contracts\User::class);
    $g->id = 'gid-burner';
    $g->email = 'burner@'.$d;
    $g->name = 'Burner';
    $g->avatar = 'https://example.com/a.png';
    $g->user = ['email_verified' => true];
    Socialite::shouldReceive('driver')->with('google')->andReturnSelf();
    Socialite::shouldReceive('user')->andReturn($g);

    $this->get('/auth/google/callback')->assertRedirect(route('login'))->assertSessionHas('error');
    expect(User::where('email', 'burner@'.$d)->exists())->toBeFalse();

    $existing = User::factory()->create(['email' => 'burner@'.$d]);
    $this->get('/auth/google/callback')->assertRedirect();
    expect(auth()->id())->toBe($existing->id);
});

test('the refresh writes a good list and keeps the old one when the download fails or looks wrong', function () {
    $good = implode('
', array_map(fn ($i) => "throwaway{$i}.example", range(1, 1500))).'
';
    Http::fake(['*' => Http::sequence()
        ->push($good)
        ->push('', 500)
        ->push('<html>not a list</html>')
        ->push('a.example
b.example
')
        ->push(str_repeat('not a domain line
', 3000))
        ->push(implode('
', array_map(fn ($i) => "few{$i}.example", range(1, 1100))).'
')
        ->pushFailedConnection('down')]);

    $this->artisan('hop:email-blocklist:refresh')->assertSuccessful();
    expect(file_exists(EmailDomains::refreshedPath()))->toBeTrue()
        ->and(EmailDomains::blocked('a@throwaway77.example'))->toBeTrue();
    $kept = file_get_contents(EmailDomains::refreshedPath());

    foreach (range(1, 6) as $_) {
        $this->artisan('hop:email-blocklist:refresh')->assertFailed();
        expect(file_get_contents(EmailDomains::refreshedPath()))->toBe($kept);
    }

    // The snapshot is still part of the list whatever the server copy holds.
    EmailDomains::forget();
    expect(EmailDomains::blocked('a@'.listedDomain()))->toBeTrue();
});

test('a new list that is under half of the current one is refused', function () {
    $big = implode('
', array_map(fn ($i) => "throwaway{$i}.example", range(1, 3000))).'
';
    $small = implode('
', array_map(fn ($i) => "throwaway-domain-number-{$i}.example", range(1, 1200))).'
';
    Http::fake(['*' => Http::sequence()->push($big)->push($small)]);

    $this->artisan('hop:email-blocklist:refresh')->assertSuccessful();
    $this->artisan('hop:email-blocklist:refresh')->assertFailed();
    expect(count(EmailDomains::parse(file_get_contents(EmailDomains::refreshedPath()))))->toBe(3000);
});

test('the refresh is scheduled weekly', function () {
    $events = collect(app(Schedule::class)->events())->map(fn ($e) => $e->command)->implode("\n");
    expect($events)->toContain('hop:email-blocklist:refresh');
});
