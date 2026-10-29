<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** The hub as SSO issuer: the ticket format PixelHop already verifies, now for every app in config/hop.php. */
class SsoIssuerTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-secret-that-is-long-enough-1234567890';

    private const STATE = 'abcdefghijklmnop1234';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('hop.sso_secret', self::SECRET);
        config()->set('hop.apps.pixelhop.url', 'https://p.example.com');
        config()->set('hop.apps.pixelhop.secret', null);
        config()->set('hop.apps.linkhop.url', 'https://link.example.com');
        config()->set('hop.apps.linkhop.secret', 'per-app-secret-that-is-long-enough-0987654321');
    }

    private function authorizeUrl(array $over = [], string $app = 'pixelhop'): string
    {
        return '/sso/'.$app.'?'.http_build_query(array_merge([
            'state' => self::STATE,
            'return_to' => $app === 'pixelhop' ? 'https://p.example.com/auth/helink/callback' : 'https://link.example.com/auth/sso/callback',
        ], $over));
    }

    private function ticketFrom(string $location, string $secret = self::SECRET): array
    {
        parse_str((string) parse_url($location, PHP_URL_QUERY), $q);
        [$body, $sig] = explode('.', $q['ticket']);
        $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', $body, $secret, true)), '+/', '-_'), '=');
        $this->assertTrue(hash_equals($expected, $sig), 'signature');

        return json_decode(base64_decode(strtr($body, '-_', '+/')), true);
    }

    private function signedIn(?User $user = null)
    {
        return $this->actingAs($user ?? User::factory()->create())->withSession(['2fa_verified' => true]);
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get($this->authorizeUrl())->assertRedirect('/login');
    }

    public function test_signed_ticket_for_verified_user_keeps_the_pixelhop_format(): void
    {
        $user = User::factory()->create();
        $res = $this->signedIn($user)->get($this->authorizeUrl());
        $location = $res->assertRedirect()->headers->get('Location');
        $this->assertStringStartsWith('https://p.example.com/auth/helink/callback?ticket=', $location);
        $t = $this->ticketFrom($location);
        $this->assertSame((string) $user->id, $t['sub']);
        $this->assertSame($user->email, $t['email']);
        $this->assertSame('pixelhop', $t['aud']);
        $this->assertSame(self::STATE, $t['state']);
        $this->assertSame(2, $t['v']);
        $this->assertFalse($t['admin']);
        $this->assertArrayHasKey('jti', $t);
        $this->assertLessThanOrEqual(time() + 120, $t['exp']);
        $this->assertGreaterThan(time() + 100, $t['exp']);
    }

    public function test_per_app_secret_wins_over_the_shared_one(): void
    {
        $location = $this->signedIn()->get($this->authorizeUrl(app: 'linkhop'))->assertRedirect()->headers->get('Location');
        $t = $this->ticketFrom($location, 'per-app-secret-that-is-long-enough-0987654321');
        $this->assertSame('linkhop', $t['aud']);
    }

    public function test_admin_flag_follows_roles(): void
    {
        $owner = User::factory()->owner()->create();
        $t = $this->ticketFrom($this->signedIn($owner)->get($this->authorizeUrl())->headers->get('Location'));
        $this->assertTrue($t['admin']);
        $this->assertContains('owner', $t['roles']);
    }

    public function test_rejects_foreign_return_address_and_bad_state(): void
    {
        $this->signedIn()->get($this->authorizeUrl(['return_to' => 'https://evil.example/auth/helink/callback']))->assertStatus(400);
        $this->signedIn()->get($this->authorizeUrl(['state' => 'x']))->assertStatus(400);
    }

    public function test_unverified_password_user_must_verify_first(): void
    {
        $user = User::factory()->create(['email_verified_at' => null, 'google_id' => null]);
        $this->signedIn($user)->get($this->authorizeUrl())->assertRedirect(route('verification.notice'));
    }

    public function test_suspended_user_gets_no_ticket(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_SUSPENDED]);
        $this->signedIn($user)->get($this->authorizeUrl())->assertRedirect(route('suspended'));
    }

    public function test_disabled_without_configuration(): void
    {
        config()->set('hop.sso_secret', null);
        $this->signedIn()->get($this->authorizeUrl())->assertStatus(404);
        config()->set('hop.sso_secret', self::SECRET);
        $this->signedIn()->get($this->authorizeUrl(app: 'nosuchapp'))->assertStatus(404);
    }

    public function test_open_sends_to_the_apps_sso_start(): void
    {
        $this->signedIn()->get('/apps/pixelhop?to=upload')->assertRedirect('https://p.example.com/auth/helink?next=%2Fupload');
        $this->signedIn()->get('/apps/linkhop')->assertRedirect('https://link.example.com/auth/sso');
    }

    public function test_new_account_from_an_app_goes_back_after_verifying(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->actingAs($user)->withSession(['2fa_verified' => true, 'url.intended' => 'http://localhost'.$this->authorizeUrl()])
            ->get($url)->assertRedirect(route('apps.open', ['app' => 'pixelhop']));
    }

    public function test_an_app_can_send_the_browser_to_logout_with_a_get(): void
    {
        config()->set('hop.apps.linkhop.logout', '/auth/sso/logout');
        config()->set('hop.apps.pixelhop.logout', null);
        config()->set('hop.apps.biohop.enabled', false);

        $this->signedIn()->get('/logout')
            ->assertRedirect('https://link.example.com/auth/sso/logout?next='.rawurlencode(route('home')));
        $this->assertGuest();

        // A visitor whose hub session already ended still runs the chain instead of a 405.
        $this->get('/logout')->assertRedirect();
    }

    public function test_logout_chains_through_apps_with_a_logout_endpoint(): void
    {
        config()->set('hop.apps.linkhop.logout', '/auth/sso/logout');
        config()->set('hop.apps.pixelhop.logout', null);
        config()->set('hop.apps.biohop.enabled', false);
        $res = $this->signedIn()->post('/logout');
        $res->assertRedirect('https://link.example.com/auth/sso/logout?next='.rawurlencode(route('home')));
        $this->assertGuest();
    }

    public function test_form_action_allows_the_public_home_page_where_the_logout_chain_ends(): void
    {
        config()->set('hop.public_url', 'https://hel.example.com');
        $csp = (string) $this->get('/login')->headers->get('Content-Security-Policy');
        $this->assertMatchesRegularExpression('#form-action [^;]*https://hel\\.example\\.com#', $csp);
        $this->assertMatchesRegularExpression('#connect-src [^;]*data:#', $csp);
    }

    public function test_two_factor_user_must_complete_totp_before_ticket_or_google_sso(): void
    {
        config()->set('services.google.client_id', 'google-client-id');
        $user = User::factory()->create([
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_confirmed_at' => now(),
        ]);

        // Defensive mfa claim: false before 2fa_verified in session, true for stateless API callers
        $this->assertFalse($user->hopProfile()['mfa']);
        $this->assertTrue($user->hopProfile(false)['mfa']);

        // Direct SSO to all configured apps must redirect to two-factor challenge
        $this->actingAs($user)->get($this->authorizeUrl(app: 'pixelhop'))
            ->assertRedirect(route('two-factor.show'));
        $this->actingAs($user)->get($this->authorizeUrl(app: 'linkhop'))
            ->assertRedirect(route('two-factor.show'));

        // SSO via Google while signed in without 2fa_verified redirects to sso.ticket -> two-factor.show
        $googleUrl = '/sso/pixelhop/google?'.http_build_query([
            'state' => self::STATE,
            'return_to' => 'https://p.example.com/auth/helink/callback',
        ]);
        $res = $this->actingAs($user)->get($googleUrl);
        $res->assertRedirect();
        $nextUrl = (string) $res->headers->get('Location');
        $this->assertStringNotContainsString('ticket=', $nextUrl);
        $this->actingAs($user)->get($nextUrl)->assertRedirect(route('two-factor.show'));

        // Once 2fa_verified is set in session, ticket is issued and mfa claim is true
        $location = $this->signedIn($user)->get($this->authorizeUrl(app: 'pixelhop'))
            ->assertRedirect()->headers->get('Location');
        $t = $this->ticketFrom($location);
        $this->assertTrue($t['mfa']);
    }
}
