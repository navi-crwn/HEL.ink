<?php

namespace Tests\Feature;

use App\Models\HopApp;
use App\Models\Role;
use App\Models\User;
use App\Support\HopApps;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The hub as the HOP ID provider for apps in the registry table (`apps`):
 * Imperium and NoteHop sign in through /sso/{app} with their own secret, and
 * call the signed HOP App Protocol API (/api/hop/*, /api/apps/{app}/*).
 */
class HopIdProviderTest extends TestCase
{
    use RefreshDatabase;

    private const IMPERIUM_SECRET = 'imperium-secret-that-is-long-enough-1234567890';

    private const NOTEHOP_SECRET = 'notehop-secret-that-is-long-enough-0987654321';

    private const STATE = 'abcdefghijklmnop1234';

    protected function setUp(): void
    {
        parent::setUp();
        HopApp::create([
            'slug' => 'imperium', 'name' => 'Imperium', 'url' => 'https://imperium.example.com',
            'callback_path' => '/auth/sso/callback', 'start_path' => '/login', 'status' => 'active',
            'secret' => self::IMPERIUM_SECRET, 'capabilities' => ['admin_client'], 'hidden' => true,
        ]);
        HopApp::create([
            'slug' => 'notehop', 'name' => 'NoteHop', 'url' => 'https://note.example.com',
            'callback_path' => '/auth/sso/callback', 'start_path' => '/auth/sso', 'status' => 'active',
            'secret' => self::NOTEHOP_SECRET, 'capabilities' => ['account_api', 'admin_api'],
        ]);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->create(['role' => Role::ADMIN, 'app_slug' => null]);

        return $user;
    }

    private function signedHeaders(string $app, string $secret, string $method, string $path, string $body = ''): array
    {
        $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $ts = (string) time();
        $nonce = $b64(random_bytes(16));
        $base = implode('|', ['hop1', $app, $ts, $nonce, strtoupper($method), $path, hash('sha256', $body)]);

        return [
            'X-HOP-App' => $app, 'X-HOP-Timestamp' => $ts, 'X-HOP-Nonce' => $nonce, 'X-HOP-Actor' => '1',
            'X-HOP-Signature' => $b64(hash_hmac('sha256', $base, $secret, true)),
        ];
    }

    private function signedGet(string $app, string $secret, string $path)
    {
        return $this->call('GET', $path, [], [], [], $this->transformHeadersToServerVars($this->signedHeaders($app, $secret, 'GET', $path) + ['Accept' => 'application/json']));
    }

    public function test_imperium_signs_in_with_its_own_registry_secret(): void
    {
        $res = $this->actingAs($this->admin())->withSession(['2fa_verified' => true])
            ->get('/sso/imperium?'.http_build_query(['state' => self::STATE, 'return_to' => 'https://imperium.example.com/auth/sso/callback']));

        $location = (string) $res->headers->get('Location');
        $this->assertStringStartsWith('https://imperium.example.com/auth/sso/callback?ticket=', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $q);
        [$body, $sig] = explode('.', $q['ticket']);
        $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', $body, self::IMPERIUM_SECRET, true)), '+/', '-_'), '=');
        $this->assertTrue(hash_equals($expected, $sig));
        $payload = json_decode(base64_decode(strtr($body, '-_', '+/')), true);
        $this->assertSame('imperium', $payload['aud']);
        $this->assertTrue($payload['admin']);
        $this->assertSame(self::STATE, $payload['state']);
    }

    public function test_hidden_registry_apps_stay_out_of_the_launcher_but_notehop_is_listed(): void
    {
        $slugs = array_map(fn ($a) => $a->slug, HopApps::all());
        $this->assertContains('notehop', $slugs);
        $this->assertNotContains('imperium', $slugs);
        $this->assertContains('https://imperium.example.com', HopApps::origins());
    }

    public function test_the_admin_api_answers_imperium_and_refuses_unsigned_or_other_apps(): void
    {
        $this->admin();
        $this->getJson('/api/hop/users')->assertStatus(404); // no X-HOP-App: not_configured

        $path = '/api/hop/users';
        $this->signedGet('imperium', self::IMPERIUM_SECRET, $path)
            ->assertOk()->assertJsonPath('ok', true)->assertJsonPath('page.total', 1);

        $this->signedGet('notehop', self::NOTEHOP_SECRET, $path)
            ->assertForbidden();

        $path = '/api/hop/stats';
        $this->signedGet('imperium', self::IMPERIUM_SECRET, $path)
            ->assertOk()->assertJsonPath('data.kpi.users', 1);

        $path = '/api/hop/health';
        $this->signedGet('notehop', self::NOTEHOP_SECRET, $path)
            ->assertOk()->assertJsonPath('data.db', 'ok');
    }

    public function test_hub_identity_and_linkhop_registry_are_separate(): void
    {
        config(['hop.apps.linkhop.url' => 'https://links.example.test', 'hop.apps.linkhop.enabled' => true]);
        $this->signedGet('imperium', self::IMPERIUM_SECRET, '/api/hop/manifest')
            ->assertOk()->assertJsonPath('data.app', 'hub')->assertJsonPath('data.name', 'HOP ID')->assertJsonPath('data.content_types', []);
        $stats = $this->signedGet('imperium', self::IMPERIUM_SECRET, '/api/hop/stats')->assertOk();
        $this->assertArrayNotHasKey('links', $stats->json('data.kpi'));
        $registry = $this->signedGet('imperium', self::IMPERIUM_SECRET, '/api/hop/apps')->assertOk();
        $links = collect($registry->json('data'))->where('slug', 'linkhop');
        $this->assertCount(1, $links);
        $this->assertSame('https://links.example.test', $links->first()['url']);
        $this->assertContains('admin_api', $links->first()['capabilities']);
    }

    public function test_imperium_can_suspend_an_account_and_it_is_audited(): void
    {
        $this->admin();
        $target = User::factory()->create();
        $path = '/api/hop/users/'.$target->id.'/actions';
        $body = json_encode(['action' => 'suspend', 'reason' => 'spam']);

        $server = $this->transformHeadersToServerVars($this->signedHeaders('imperium', self::IMPERIUM_SECRET, 'POST', $path, $body) + ['Content-Type' => 'application/json']);
        $this->call('POST', $path, [], [], [], $server, $body)
            ->assertOk()->assertJsonPath('data.status', User::STATUS_SUSPENDED);
        $this->assertDatabaseHas('hop_audit', ['action' => 'suspend', 'target_id' => (string) $target->id, 'app_slug' => 'imperium']);
    }

    public function test_an_app_sign_in_form_checks_hop_id_credentials(): void
    {
        $user = User::factory()->create(['email' => 'reader@example.com', 'password' => Hash::make('correct horse')]);
        $path = '/api/apps/notehop/login';
        $call = function (string $password) use ($path) {
            $body = json_encode(['email' => 'reader@example.com', 'password' => $password, 'ip' => '203.0.113.9']);
            $server = $this->transformHeadersToServerVars($this->signedHeaders('notehop', self::NOTEHOP_SECRET, 'POST', $path, $body) + ['Content-Type' => 'application/json']);

            return $this->call('POST', $path, [], [], [], $server, $body);
        };

        $call('wrong')->assertStatus(401)->assertJsonPath('error', 'bad_credentials');
        $call('correct horse')->assertOk()->assertJsonPath('user.sub', (string) $user->id);
    }

    public function test_admins_land_in_imperium_after_signing_in_and_others_on_the_launcher(): void
    {
        config()->set('hop.control_url', 'https://citadel.example.com');
        config()->set('services.turnstile.secret', null);
        $admin = $this->admin();
        $admin->forceFill(['password' => Hash::make('password')])->save();

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect('https://citadel.example.com');

        auth()->logout();
        $member = User::factory()->create(['password' => Hash::make('password')]);
        $this->post('/login', ['email' => $member->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard', absolute: false));
    }
}
