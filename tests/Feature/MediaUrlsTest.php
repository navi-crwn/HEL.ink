<?php

use App\Models\HopApp;
use App\Models\User;
use App\Services\SsoTicket;
use App\Support\Avatar;
use App\Support\HopApp as HopAppSupport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
| Files live on one host and are asked for from another: the hub stores avatars,
| the apps (link., bio., pixel., note.) show them. Every URL the hub prints or
| sends must therefore be absolute and point at the host that serves the file.
*/

const MEDIA_HUB = 'https://hub.example.test';

beforeEach(function () {
    config()->set('hop.hub_url', MEDIA_HUB);
    config()->set('hop.public_url', 'https://public.example.test');
});

function signedMediaGet(string $path): array
{
    $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    $ts = (string) time();
    $nonce = $b64(random_bytes(16));
    $base = implode('|', ['hop1', 'notehop', $ts, $nonce, 'GET', $path, hash('sha256', '')]);

    return [
        'X-HOP-App' => 'notehop', 'X-HOP-Timestamp' => $ts, 'X-HOP-Nonce' => $nonce, 'X-HOP-Actor' => '1',
        'X-HOP-Signature' => $b64(hash_hmac('sha256', $base, 'notehop-secret-that-is-long-enough-0987654321', true)),
        'Accept' => 'application/json',
    ];
}

function registerMediaApp(): void
{
    HopApp::create([
        'slug' => 'notehop', 'name' => 'NoteHop', 'url' => 'https://note.example.test',
        'callback_path' => '/auth/sso/callback', 'start_path' => '/auth/sso', 'status' => 'active',
        'secret' => 'notehop-secret-that-is-long-enough-0987654321', 'capabilities' => ['account_api'],
    ]);
}

test('Avatar::url makes every stored shape an absolute URL on the hub host', function (string $stored, string $expected) {
    expect(Avatar::url($stored))->toBe($expected);
})->with([
    'bare disk path' => ['avatars/7-abc.jpg', MEDIA_HUB.'/storage/avatars/7-abc.jpg'],
    'storage-relative' => ['/storage/avatars/7-abc.jpg', MEDIA_HUB.'/storage/avatars/7-abc.jpg'],
    'storage without slash' => ['storage/avatars/7-abc.jpg', MEDIA_HUB.'/storage/avatars/7-abc.jpg'],
    'public disk prefix' => ['public/avatars/7-abc.jpg', MEDIA_HUB.'/storage/avatars/7-abc.jpg'],
    'leading slash only' => ['/avatars/7-abc.jpg', MEDIA_HUB.'/storage/avatars/7-abc.jpg'],
    'protocol-relative' => ['//lh3.googleusercontent.com/a/x', 'https://lh3.googleusercontent.com/a/x'],
    'full https URL is kept' => ['https://lh3.googleusercontent.com/a/x=s96', 'https://lh3.googleusercontent.com/a/x=s96'],
]);

test('Avatar::url is null for an empty value', function () {
    expect(Avatar::url(null))->toBeNull()->and(Avatar::url(''))->toBeNull()->and(Avatar::url('   '))->toBeNull();
});

test('the user picture is absolute: uploaded first, else the external one', function () {
    $uploaded = User::factory()->create(['avatar_path' => '/storage/avatars/1-x.jpg', 'avatar' => 'https://lh3.example/g.jpg']);
    $google = User::factory()->create(['avatar_path' => null, 'avatar' => 'https://lh3.example/g.jpg']);
    $legacy = User::factory()->create(['avatar_path' => null, 'avatar' => '/storage/avatars/2-y.jpg']);
    $none = User::factory()->create(['avatar_path' => null, 'avatar' => null]);

    expect($uploaded->avatarUrl())->toBe(MEDIA_HUB.'/storage/avatars/1-x.jpg')
        ->and($google->avatarUrl())->toBe('https://lh3.example/g.jpg')
        ->and($legacy->avatarUrl())->toBe(MEDIA_HUB.'/storage/avatars/2-y.jpg')
        ->and($none->avatarUrl())->toBeNull();
});

test('the HOP profile sent to the apps carries an absolute avatar URL', function () {
    $user = User::factory()->create(['avatar_path' => 'avatars/3-z.jpg']);
    $profile = $user->hopProfile();

    expect($profile['avatar'])->toBe(MEDIA_HUB.'/storage/avatars/3-z.jpg')
        ->and($profile['avatar_url'])->toBe($profile['avatar']);
    expect(User::factory()->create(['avatar_path' => null, 'avatar' => null])->hopProfile()['avatar'])->toBe('');
});

test('the SSO ticket payload carries an absolute avatar URL', function () {
    config()->set('hop.sso_secret', 'test-secret-that-is-long-enough-1234567890');
    config()->set('hop.apps.linkhop.url', 'https://link.example.test');
    $user = User::factory()->create(['avatar_path' => 'avatars/4-w.jpg']);
    $ticket = SsoTicket::issue($user, HopAppSupport::fromConfig('linkhop', config('hop.apps.linkhop')), 'abcdefghijklmnop1234');
    [$body] = explode('.', $ticket);
    $payload = json_decode(base64_decode(strtr($body, '-_', '+/')), true);

    expect($payload['avatar'])->toBe(MEDIA_HUB.'/storage/avatars/4-w.jpg')
        ->and($payload['avatar_url'])->toBe(MEDIA_HUB.'/storage/avatars/4-w.jpg');
});

test('the signed profile API returns absolute avatar URLs, one and in bulk', function () {
    registerMediaApp();
    $a = User::factory()->create(['avatar_path' => '/storage/avatars/5-a.jpg']);
    $b = User::factory()->create(['avatar_path' => null, 'avatar' => '/storage/avatars/6-b.jpg']);

    $one = '/api/hop/profile/'.$a->id;
    $this->call('GET', $one, [], [], [], $this->transformHeadersToServerVars(signedMediaGet($one)))
        ->assertOk()
        ->assertJsonPath('data.avatar', MEDIA_HUB.'/storage/avatars/5-a.jpg')
        ->assertJsonPath('data.avatar_url', MEDIA_HUB.'/storage/avatars/5-a.jpg');

    $bulk = '/api/hop/profiles';
    $res = $this->call('GET', $bulk, [], [], [], $this->transformHeadersToServerVars(signedMediaGet($bulk)))->assertOk();
    $avatars = collect($res->json('data'))->pluck('avatar_url', 'hop_id');
    expect($avatars[(string) $a->id])->toBe(MEDIA_HUB.'/storage/avatars/5-a.jpg')
        ->and($avatars[(string) $b->id])->toBe(MEDIA_HUB.'/storage/avatars/6-b.jpg');
});

test('a stored photo is shown on the settings page with an absolute URL', function () {
    $user = User::factory()->create(['avatar_path' => 'avatars/8-s.jpg']);

    $this->actingAs($user)->withSession(['2fa_verified' => true])->get('/settings')
        ->assertOk()
        ->assertSee('src="'.MEDIA_HUB.'/storage/avatars/8-s.jpg"', false);
});

test('an uploaded photo is stored on the public disk and served from the hub host', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)->withSession(['2fa_verified' => true])
        ->post('/profile/avatar', ['avatar' => UploadedFile::fake()->image('me.png', 300, 300)])
        ->assertSessionHasNoErrors();

    $user->refresh();
    Storage::disk('public')->assertExists($user->avatar_path);
    expect($user->avatarUrl())->toBe(MEDIA_HUB.'/storage/'.$user->avatar_path);
});

test('deleting a photo finds the file even when the stored value had a storage prefix', function () {
    Storage::fake('public');
    Storage::disk('public')->put('avatars/9-d.jpg', 'x');
    $user = User::factory()->create(['avatar_path' => '/storage/avatars/9-d.jpg']);

    Avatar::delete($user);

    Storage::disk('public')->assertMissing('avatars/9-d.jpg');
});

test('the public disk URL has no double slash when APP_URL ends with one', function () {
    $saved = [$_ENV['APP_URL'] ?? null, $_SERVER['APP_URL'] ?? null];
    $_ENV['APP_URL'] = $_SERVER['APP_URL'] = 'https://hub.example.test/';
    try {
        $config = require base_path('config/filesystems.php');
    } finally {
        [$_ENV['APP_URL'], $_SERVER['APP_URL']] = $saved;
    }
    expect($config['disks']['public']['url'])->toBe('https://hub.example.test/storage');
});

test('the 500 page does not load anything from a CDN', function () {
    $html = view('errors.500', ['exception' => new Exception('boom')])->render();

    expect($html)->not->toContain('cdn.tailwindcss.com')->not->toContain('laravel.com/img');
});

test('every image, icon, stylesheet and script the public pages print is absolute or a file this host serves', function (string $path) {
    $html = $this->get($path)->assertOk()->getContent();
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
    $urls = [];
    foreach ((new DOMXPath($dom))->query('//img[@src]|//source[@srcset]|//script[@src]|//link[@href][@rel="stylesheet" or contains(@rel,"icon")]|//meta[@property="og:image"]') as $node) {
        $urls[] = $node->getAttribute('src') ?: $node->getAttribute('srcset') ?: $node->getAttribute('href') ?: $node->getAttribute('content');
    }
    expect($urls)->not->toBeEmpty();
    foreach ($urls as $url) {
        $url = trim(explode(' ', trim($url))[0]);
        if (str_starts_with($url, 'data:') || $url === '') {
            continue;
        }
        if (preg_match('#^https?://#', $url)) {
            expect($url)->not->toContain('cdn.');

            continue;
        }
        expect($url)->toStartWith('/');
        $file = public_path(parse_url($url, PHP_URL_PATH));
        if (! is_file($file)) {
            $this->get($url)->assertOk(); // a route such as /brand/{v}/logo.png
        }
    }
})->with(['/', '/about', '/products', '/privacy', '/status', '/changelog']);
