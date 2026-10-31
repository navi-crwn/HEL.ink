<?php

namespace Tests\Feature;

use App\Http\Controllers\SsoController;
use App\Jobs\QueueHeartbeat;
use App\Jobs\SendHopEvent;
use App\Models\HopApp;
use App\Models\User;
use App\Services\UserDeletion;
use App\Support\HopSignature;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HopAccountEventsTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'notehop-secret-that-is-long-enough-0987654321';

    public function test_profile_status_and_deletion_events_reach_listening_apps(): void
    {
        Queue::fake();
        HopApp::factory()->notehop(self::SECRET)->create(['capabilities' => ['events']]);
        HopApp::factory()->pixelhop()->create(['capabilities' => []]);

        $u = User::factory()->create(['username' => 'rani']);
        $u->update(['bio' => 'new bio']);
        Queue::assertPushed(SendHopEvent::class, fn ($j) => $j->type === 'profile.updated' && $j->data['bio'] === 'new bio' && $j->data['profile_version'] === 2 && $j->data['hop_id'] === (string) $u->id);

        $u->update(['status' => User::STATUS_SUSPENDED]);
        Queue::assertPushed(SendHopEvent::class, fn ($j) => $j->type === 'account.status' && $j->data['status'] === 'suspended' && $j->data['sub'] === (string) $u->id && $j->data['hop_id'] === (string) $u->id);

        $u->update(['status' => User::STATUS_ACTIVE]);
        Queue::assertPushed(SendHopEvent::class, fn ($j) => $j->type === 'account.status' && $j->data['status'] === 'active');

        $u->forceFill(['last_login_at' => now()])->saveQuietly();
        $u->delete();
        Queue::assertPushed(SendHopEvent::class, fn ($j) => $j->type === 'account.deleted' && $j->data['sub'] === (string) $u->id);

        Queue::assertPushed(SendHopEvent::class, 4);
    }

    public function test_event_delivery_is_signed_with_the_apps_secret(): void
    {
        $app = HopApp::factory()->notehop(self::SECRET)->create(['capabilities' => ['events']]);
        Http::fake(['https://note.example.com/hop/events' => Http::response(['ok' => true])]);

        (new SendHopEvent($app->id, 'profile.updated', ['sub' => '1', 'hop_id' => '1'], 'evt-1', '2026-01-01T00:00:00Z'))->handle();

        Http::assertSent(function ($req) {
            $base = HopSignature::stringToSign('helink', $req->header('X-HOP-Timestamp')[0], $req->header('X-HOP-Nonce')[0], 'POST', '/hop/events', $req->body());
            $expected = HopSignature::b64(hash_hmac('sha256', $base, self::SECRET, true));

            return $req->header('X-HOP-Signature')[0] === $expected && json_decode($req->body(), true)['type'] === 'profile.updated';
        });
    }

    public function test_user_deletion_removes_avatar_qr_codes_and_bio_uploads(): void
    {
        Storage::fake('public');
        Queue::fake();
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
        config()->set('hop.sso_secret', self::SECRET);
        config()->set('hop.apps.linkhop.url', 'https://link.hel.ink');
        config()->set('hop.apps.biohop.url', 'https://bio.hel.ink');

        if (! Schema::hasTable('links')) {
            Schema::create('links', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('user_id')->nullable();
                $t->string('slug')->unique();
                $t->string('target_url');
                $t->string('qr_code_path')->nullable();
            });
        }
        if (! Schema::hasTable('bio_pages')) {
            Schema::create('bio_pages', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('user_id');
                $t->string('slug')->unique();
                $t->string('avatar_url')->nullable();
                $t->string('background_image')->nullable();
                $t->string('background_value')->nullable();
                $t->text('qr_settings')->nullable();
            });
        }
        if (! Schema::hasTable('bio_links')) {
            Schema::create('bio_links', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('bio_page_id');
                $t->string('thumbnail_url')->nullable();
                $t->string('custom_icon')->nullable();
                $t->text('content')->nullable();
            });
        }

        HopApp::factory()->notehop(self::SECRET)->create(['capabilities' => ['events']]);
        $u = User::factory()->create(['avatar_path' => 'avatars/hub-user.png']);
        Storage::disk('public')->put('avatars/hub-user.png', 'png');
        Storage::disk('public')->put('qrcodes/link1.svg', 'svg');
        Storage::disk('public')->put('bio-images/bg1.jpg', 'jpg');
        Storage::disk('public')->put('bio-thumbnails/thumb1.png', 'png');

        DB::table('links')->insert([
            'user_id' => $u->id,
            'slug' => 'slug1',
            'target_url' => 'https://example.com',
            'qr_code_path' => 'qrcodes/link1.svg',
        ]);
        $pageId = DB::table('bio_pages')->insertGetId([
            'user_id' => $u->id,
            'slug' => 'bio1',
            'avatar_url' => '/storage/bio-images/bg1.jpg',
        ]);
        DB::table('bio_links')->insert([
            'bio_page_id' => $pageId,
            'thumbnail_url' => 'bio-thumbnails/thumb1.png',
        ]);

        app(UserDeletion::class)->delete($u);

        Storage::disk('public')->assertMissing('avatars/hub-user.png');
        Storage::disk('public')->assertMissing('qrcodes/link1.svg');
        Storage::disk('public')->assertMissing('bio-images/bg1.jpg');
        Storage::disk('public')->assertMissing('bio-thumbnails/thumb1.png');

        Queue::assertPushed(SendHopEvent::class, fn ($j) => $j->type === 'account.deleted'
            && in_array('qrcodes/link1.svg', $j->data['qr_codes'] ?? [], true)
            && in_array('bio-images/bg1.jpg', $j->data['bio_uploads'] ?? [], true));

        Http::assertSent(fn ($req) => $req->method() === 'DELETE'
            && $req->url() === "https://link.hel.ink/internal/accounts/{$u->id}"
            && ! empty($req->header('X-Hop-Signature')[0]));
    }

    public function test_health_reports_hub_app_and_live_queue_after_heartbeat(): void
    {
        HopApp::factory()->notehop(self::SECRET)->create(['capabilities' => ['admin_api']]);
        (new QueueHeartbeat)->handle();

        $path = '/api/hop/health';
        $ts = (string) time();
        $nonce = HopSignature::b64(random_bytes(16));
        $sig = HopSignature::b64(hash_hmac('sha256', HopSignature::stringToSign('notehop', $ts, $nonce, 'GET', $path, ''), self::SECRET, true));

        $res = $this->call('GET', $path, [], [], [], $this->transformHeadersToServerVars([
            'X-HOP-App' => 'notehop',
            'X-HOP-Timestamp' => $ts,
            'X-HOP-Nonce' => $nonce,
            'X-HOP-Signature' => $sig,
            'Accept' => 'application/json',
        ]));

        $res->assertOk()
            ->assertJsonPath('data.app', 'hub')
            ->assertJsonPath('data.db', 'ok')
            ->assertJsonPath('data.queue', 'ok');
    }

    public function test_default_logout_paths_for_pixelhop_and_notehop_are_configured(): void
    {
        config()->set('hop.sso_secret', self::SECRET);
        HopApp::factory()->notehop(self::SECRET)->create();

        $this->assertSame('/auth/helink/logout', config('hop.apps.pixelhop.logout'));
        $this->assertSame('/auth/sso/logout', config('hop.apps.notehop.logout'));

        $chain = SsoController::logoutChain('https://hel.ink');
        $decoded = urldecode(urldecode(urldecode($chain)));
        $this->assertStringContainsString('https://pixel.hel.ink/auth/helink/logout', $decoded);
        $this->assertStringContainsString('https://note.example.com/auth/sso/logout', $decoded);
    }
}
