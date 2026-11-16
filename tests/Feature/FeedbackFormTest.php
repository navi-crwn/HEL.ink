<?php

use App\Http\Controllers\FeedbackController;
use App\Models\FeedbackReport;
use App\Models\HopApp;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

/*
 * The Feedback form: /feedback and /feedback/sent for guests and members, every refusal, the screenshot rules,
 * the switch, and the two hosts. Who can read the reports is tested in FeedbackApiTest.
 */

function fbImperium(array $caps = ['admin_client']): HopApp
{
    return HopApp::create(['slug' => 'imperium', 'name' => 'Imperium', 'url' => 'https://imperium.example.test',
        'callback_path' => '/auth/sso/callback', 'start_path' => '/login', 'status' => 'active',
        'secret' => 'local-test-imperium-secret-long-enough', 'capabilities' => $caps, 'hidden' => true]);
}

function fbJpeg(int $w = 40, int $h = 30): string
{
    $im = imagecreatetruecolor($w, $h);
    imagefill($im, 0, 0, imagecolorallocate($im, 200, 40, 40));
    ob_start();
    imagejpeg($im);

    return (string) ob_get_clean();
}

function fbPng(int $w = 40, int $h = 30): string
{
    $im = imagecreatetruecolor($w, $h);
    ob_start();
    imagepng($im);

    return (string) ob_get_clean();
}

function fbGif(): string
{
    $im = imagecreatetruecolor(10, 10);
    ob_start();
    imagegif($im);

    return (string) ob_get_clean();
}

function fbWebp(): string
{
    $im = imagecreatetruecolor(20, 20);
    ob_start();
    imagewebp($im);

    return (string) ob_get_clean();
}

/** A JPEG with an EXIF block that carries a marker we can look for. */
function fbExifJpeg(string $marker = 'SECRET-GPS-LOCATION'): string
{
    $payload = "Exif\0\0MM\0\x2A\0\0\0\x08\0\0\0\0\0\0".$marker;

    return substr(fbJpeg(), 0, 2)."\xFF\xE1".pack('n', strlen($payload) + 2).$payload.substr(fbJpeg(), 2);
}

function fbFile(string $name, string $bytes): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $bytes);
}

/** @param array<string, mixed> $over */
/** A send. The guest counters are cleared first (each test is "a new visitor") unless the test is about the counters. */
function fbSend($test, array $over = [], ?UploadedFile $image = null, bool $reset = true)
{
    if ($reset) {
        RateLimiter::clear('feedback:g:127.0.0.1:h');
        RateLimiter::clear('feedback:g:127.0.0.1:d');
    }
    $data = array_merge(['kind' => 'problem', 'message' => 'The Save button does nothing when I click it.', 'app' => 'pixelhop',
        'from' => 'view', 'v' => '3.14.0', 'viewport' => '1440x900'], $over);
    if ($image) {
        $data['image'] = $image;
    }

    return $test->post('/feedback', $data);
}

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('local');
    fbImperium();
});

// ---- the form ----------------------------------------------------------------

test('a guest opens the form from an app link with the app, screen and version filled in', function () {
    $html = $this->withoutVite()->get('/feedback?app=pixelhop&from=view&v=3.14.0')->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')->getContent();
    expect($html)->toContain('name="robots" content="noindex, nofollow"')
        ->and($html)->not->toContain('rel="canonical"')->and($html)->not->toContain('<link rel="alternate"')
        ->and($html)->toContain('<option value="pixelhop" selected>PixelHop</option>')
        ->and($html)->toContain('PixelHop · view')->and($html)->toContain('3.14.0')
        ->and($html)->toContain('Sending as a guest')->and($html)->toContain('Sent with your report')
        ->and($html)->toContain('name="company_url"')->and($html)->toContain('name="viewport"')
        ->and($html)->toContain('Please do not write passwords')->and($html)->toContain(route('privacy'));
    // The text, the kinds and the screenshot field are there; nothing asks for an e-mail address.
    expect($html)->toContain('name="message"')->and($html)->toContain('value="idea"')->and($html)->toContain('name="image"')
        ->and($html)->not->toContain('type="email"');
});

test('links that are wrong are dropped, never an error', function () {
    $html = $this->withoutVite()->get('/feedback?app=evil&from='.urlencode('/n/abc123?key=secret#frag').'&v='.urlencode('<script>'))->assertOk()->getContent();
    expect($html)->toContain('<option value="hub" selected>HOP ID</option>')
        ->and($html)->toContain('<input type="hidden" name="from" value="">')->and($html)->toContain('<input type="hidden" name="v" value="">')
        ->and($html)->not->toContain('Sent with your report</h2>'.PHP_EOL.'abc')->and($html)->not->toContain('HOP ID · /n/');
    $this->withoutVite()->get('/feedback?app[]=x&from[]=y&v[]=z')->assertOk();
    $this->withoutVite()->get('/feedback')->assertOk()->assertSee('HOP ID');
    // A screen name is a route name or template, so these shapes are kept.
    $this->withoutVite()->get('/feedback?app=notehop&from='.urlencode('/n/{id}'))->assertOk()->assertSee('NoteHop · /n/{id}', false);
    $this->withoutVite()->get('/feedback?app=hub&from='.str_repeat('a', 81))->assertOk()->assertSee('<input type="hidden" name="from" value="">', false);
});

test('a member is recognised and the report keeps the username', function () {
    $user = User::factory()->create(['username' => 'helena']);
    $this->actingAs($user)->withoutVite()->get('/feedback?app=linkhop')->assertOk()->assertSee('Sending as @helena')
        ->assertDontSee('Sending as a guest');
    fbSend($this->actingAs($user))->assertRedirect(route('feedback.sent'));
    $r = FeedbackReport::sole();
    expect($r->user_id)->toBe($user->id)->and($r->username)->toBe('helena')->and($r->app)->toBe('pixelhop');
});

test('a guest report keeps what the contract lists and the real address', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])->withHeader('User-Agent', 'Mozilla/5.0 Test')
        ->post('/feedback', ['kind' => 'idea', 'message' => "A dark map would help.\nThanks!", 'app' => 'filehop', 'from' => 'home', 'v' => '1.2.3+b4', 'viewport' => '390x844'])
        ->assertRedirect(route('feedback.sent'));
    $r = FeedbackReport::sole();
    expect($r->kind)->toBe('idea')->and($r->status)->toBe('new')->and($r->app)->toBe('filehop')->and($r->page)->toBe('home')
        ->and($r->app_version)->toBe('1.2.3+b4')->and($r->message)->toBe("A dark map would help.\nThanks!")
        ->and($r->user_id)->toBeNull()->and($r->username)->toBeNull()->and($r->ip_address)->toBe('203.0.113.7')
        ->and($r->user_agent)->toBe('Mozilla/5.0 Test')->and($r->viewport)->toBe('390x844')->and($r->locale)->toBe('en')
        ->and($r->screenshot_path)->toBeNull()->and($r->read_at)->toBeNull()->and($r->done_at)->toBeNull();
});

test('the text is kept as typed: markup is stored and only escaped when shown', function () {
    $text = 'Broken: <b>bold</b> & "quotes" <script>alert(1)</script> http://x.test';
    fbSend($this, ['message' => $text]);
    expect(FeedbackReport::sole()->message)->toBe($text);
});

test('unknown screen, version and viewport values are stored as nothing, not as typed', function () {
    fbSend($this, ['app' => 'nonsense', 'from' => '/n/abc?k=1', 'v' => 'x y', 'viewport' => 'huge']);
    $r = FeedbackReport::sole();
    expect($r->app)->toBe('hub')->and($r->page)->toBeNull()->and($r->app_version)->toBeNull()->and($r->viewport)->toBeNull();
});

test('the thank-you page links back to the hub for a hub report and offers to send again; reloading sends nothing', function () {
    fbSend($this, ['app' => 'hub', 'from' => 'dashboard', 'v' => '1.6.0'])->assertSessionHas('feedback.sent', ['app' => 'hub', 'no_room' => false]);
    $html = $this->withSession(['feedback.sent' => ['app' => 'hub', 'no_room' => false]])->withoutVite()->get('/feedback/sent')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')->getContent();
    expect($html)->toContain('Thank you')->and($html)->toContain(route('home'))->and($html)->toContain('Send more feedback');
    $this->withSession(['feedback.sent' => ['app' => 'hub', 'no_room' => false]])->withoutVite()->get('/feedback/sent')->assertOk();
    expect(FeedbackReport::count())->toBe(1);
    // Opened directly, with nothing just sent, it is the form again.
    $this->flushSession();
    $this->get('/feedback/sent')->assertRedirect(route('feedback.create'));
});

// ---- refusals: each has its own message and the typed text stays --------------

test('too short, too long and no kind are refused with their own message and keep the text', function () {
    fbSend($this, ['message' => 'too short'])->assertSessionHasErrors(['message' => 'Write at least 10 characters.'])->assertSessionHasInput('message', 'too short');
    fbSend($this, ['message' => str_repeat('a', 4001)])->assertSessionHasErrors(['message' => 'Keep it to 4,000 characters or fewer.']);
    fbSend($this, ['message' => str_repeat('a', 4000)])->assertSessionHasNoErrors();
    fbSend($this, ['kind' => '', 'message' => 'A long enough message to keep.'])->assertSessionHasErrors(['kind' => 'Choose what kind of feedback this is.'])
        ->assertSessionHasInput('message', 'A long enough message to keep.');
    fbSend($this, ['kind' => 'complaint'])->assertSessionHasErrors('kind');
    fbSend($this, ['message' => ['x']])->assertSessionHasErrors('message');
    expect(FeedbackReport::count())->toBe(1);
    // The refusals show on the form, in the language of the page, with the text still in the box.
    $page = $this->withSession(['errors' => (new ViewErrorBag)->put('default', new MessageBag(['message' => ['Write at least 10 characters.']]))])
        ->withoutVite()->get('/feedback?lang=id');
    $page->assertOk()->assertSee('Laporan Anda belum terkirim');
});

test('the form and its refusals follow the language in all nine languages', function () {
    foreach (['en', 'zh-CN', 'de', 'id', 'ja', 'pt-BR', 'es', 'fr', 'ru'] as $lang) {
        $map = json_decode(file_get_contents(lang_path($lang.'.json')), true);
        $html = $this->withoutVite()->get('/feedback?lang='.$lang.'&app=pixelhop')->assertOk()->getContent();
        foreach (['Send feedback', 'Problem', 'Idea or suggestion', 'Your message', 'Screenshot (optional)', 'Sent with your report', 'Send'] as $key) {
            expect($map[$key] ?? '')->not->toBe('', "$lang lacks $key");
            expect($html)->toContain(e($map[$key]));
        }
        foreach (['That image is too large. Use one up to 5 MB.', 'Use a PNG, JPEG or WebP image.', 'That file could not be read as an image.',
            'You have sent a lot of feedback already. Please try again later.', 'The security check failed. Please try again.',
            'Write at least 10 characters.', 'Keep it to 4,000 characters or fewer.', 'Choose what kind of feedback this is.',
            'Thank you', 'Back to :app', 'Send more feedback'] as $key) {
            expect($map[$key] ?? '')->not->toBe('', "$lang lacks $key");
            if ($lang !== 'en') {
                expect($map[$key])->not->toBe($key, "$lang did not translate $key");
            }
        }
    }
});

test('a failed security check refuses a guest, and a member is not asked', function () {
    config(['services.turnstile.secret' => 'secret']);
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);
    fbSend($this, ['cf-turnstile-response' => 'bad'])->assertSessionHasErrors(['security' => 'The security check failed. Please try again.'])
        ->assertSessionHasInput('message');
    expect(FeedbackReport::count())->toBe(0);
    Http::swap(new HttpFactory);
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);
    fbSend($this, ['cf-turnstile-response' => 'good'])->assertRedirect(route('feedback.sent'));
    Http::swap(new HttpFactory);
    Http::fake();
    fbSend($this->actingAs(User::factory()->create()), ['message' => 'A member never sees the check.'])->assertRedirect(route('feedback.sent'));
    Http::assertNothingSent();
});

test('in production without Turnstile keys a guest is refused, a member is not', function () {
    app()->detectEnvironment(fn () => 'production'); // outside "testing" the CSRF check is on, so this test skips it
    $this->withoutMiddleware(ValidateCsrfToken::class);
    config(['services.turnstile.secret' => '']);
    fbSend($this)->assertSessionHasErrors('security');
    expect(FeedbackReport::count())->toBe(0);
    fbSend($this->actingAs(User::factory()->create()))->assertRedirect(route('feedback.sent'));
    app()->detectEnvironment(fn () => 'testing');
});

test('a robot that fills the trap field is answered as if it worked and nothing is kept', function () {
    fbSend($this, ['company_url' => 'https://spam.test'])->assertRedirect(route('feedback.sent'));
    expect(FeedbackReport::count())->toBe(0)->and(Storage::disk('local')->allFiles())->toBe([]);
});

test('the same sender with the same text within a minute is one report', function () {
    fbSend($this)->assertRedirect(route('feedback.sent'));
    fbSend($this)->assertRedirect(route('feedback.sent'));
    expect(FeedbackReport::count())->toBe(1);
    fbSend($this, ['message' => 'Another report entirely, different text.']);
    expect(FeedbackReport::count())->toBe(2);
    $this->travel(61)->seconds();
    fbSend($this);
    expect(FeedbackReport::count())->toBe(3);
});

test('a second quick send with a screenshot does not leave a second file behind', function () {
    fbSend($this, [], fbFile('s.png', fbPng()))->assertRedirect(route('feedback.sent'));
    fbSend($this, [], fbFile('s.png', fbPng()))->assertRedirect(route('feedback.sent'));
    expect(FeedbackReport::count())->toBe(1)->and(Storage::disk('local')->allFiles('feedback'))->toHaveCount(1);
});

test('a guest may send 3 an hour and 10 a day per address, with a 429 that keeps the text', function () {
    foreach ([1, 2, 3] as $i) {
        fbSend($this, ['message' => "Report number $i from the same address."], null, false)->assertRedirect(route('feedback.sent'));
    }
    $r = fbSend($this, ['message' => 'The fourth one, which must not be lost.'], null, false);
    $r->assertStatus(429)->assertSee('You have sent a lot of feedback already')->assertSee('The fourth one, which must not be lost.')->assertHeader('Retry-After');
    expect(FeedbackReport::count())->toBe(3);
    // Another address has its own count.
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.9'])->post('/feedback', ['kind' => 'idea', 'message' => 'A different visitor entirely.'])->assertRedirect(route('feedback.sent'));
    // The day limit: ten in a day, even when each hour is free.
    $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1']);
    RateLimiter::clear('feedback:g:127.0.0.1:h');
    for ($i = 0; $i < 10; $i++) {
        RateLimiter::hit('feedback:g:127.0.0.1:d', 86400);
    }
    fbSend($this, ['message' => 'Over the daily limit already.'], null, false)->assertStatus(429);
});

test('a member may send 10 an hour per account, not per address', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    foreach (range(1, 10) as $i) {
        fbSend($this->actingAs($a), ['message' => "Member report number $i, long enough."])->assertRedirect(route('feedback.sent'));
    }
    fbSend($this->actingAs($a), ['message' => 'The eleventh of the same member.'])->assertStatus(429)->assertSee('The eleventh of the same member.');
    fbSend($this->actingAs($b), ['message' => 'Another member, the same address.'])->assertRedirect(route('feedback.sent'));
    expect(FeedbackReport::count())->toBe(11);
});

test('a body bigger than PHP accepts comes back to the form with the size message, not a bare error page', function () {
    $r = $this->call('POST', '/feedback?app=pixelhop&from=view', [], [], [], ['CONTENT_LENGTH' => 99_999_999, 'CONTENT_TYPE' => 'multipart/form-data; boundary=x']);
    $r->assertRedirect('/feedback?app=pixelhop&from=view&e=size');
    $this->withoutVite()->get('/feedback?app=pixelhop&from=view&e=size')->assertOk()->assertSee('That image is too large. Use one up to 5 MB.')
        ->assertSee('value="pixelhop" selected', false);
    expect(FeedbackReport::count())->toBe(0);
    // Other posts that are too large keep the usual answer.
    $this->withoutVite()->call('POST', '/login', [], [], [], ['CONTENT_LENGTH' => 99_999_999])->assertStatus(413);
});

// ---- the switch --------------------------------------------------------------

test('with the switch off the form, the thank-you page and the post answer 404 and no link is shown', function () {
    config(['hop.feedback.enabled' => false]);
    $this->withoutVite()->get('/feedback')->assertNotFound();
    $this->withoutVite()->get('/feedback/sent')->assertNotFound();
    fbSend($this)->assertNotFound();
    expect(FeedbackReport::count())->toBe(0);
    $this->withoutVite()->get('/help')->assertOk()->assertDontSee('/feedback?');
    $this->actingAs(User::factory()->create())->withoutVite()->get('/dashboard')->assertOk()->assertDontSee('/feedback?');
});

test('without an app that may read reports the feature is off, whatever the switch says', function () {
    HopApp::query()->delete();
    $this->withoutVite()->get('/feedback')->assertNotFound();
    fbImperium(['account_api']); // registered, but without admin_client
    $this->withoutVite()->get('/feedback')->assertNotFound();
    $this->withoutVite()->get('/contact')->assertOk()->assertDontSee('/feedback?');
    HopApp::query()->update(['capabilities' => ['admin_client']]);
    $this->withoutVite()->get('/feedback')->assertOk();
});

// ---- the screenshot [PEKA] ----------------------------------------------------

test('a JPEG with EXIF is stored again without it, as a new JPEG on the private disk', function () {
    $original = fbExifJpeg('SECRET-GPS-LOCATION');
    expect($original)->toContain('SECRET-GPS-LOCATION'); // the fixture really carries it
    fbSend($this, [], fbFile('phone photo (1).jpg', $original))->assertRedirect(route('feedback.sent'));
    $r = FeedbackReport::sole();
    expect($r->screenshot_path)->toMatch('#^feedback/\d{4}/\d{2}/[0-9a-f]{40}\.jpg$#');
    $bytes = Storage::disk('local')->get($r->screenshot_path);
    expect(substr($bytes, 0, 3))->toBe("\xFF\xD8\xFF")->and($bytes)->not->toContain('SECRET-GPS-LOCATION')->and($bytes)->not->toContain('Exif');
    expect(getimagesizefromstring($bytes)[2])->toBe(IMAGETYPE_JPEG);
    // Nothing from the visitor is in the name or the path.
    expect($r->screenshot_path)->not->toContain('phone');
});

test('PNG and WebP are accepted and come out as JPEG; a big picture is brought down to 2560 px', function () {
    fbSend($this, [], fbFile('a.png', fbPng(3000, 1000)))->assertRedirect(route('feedback.sent'));
    fbSend($this, ['message' => 'The WebP one, long enough here.'], fbFile('b.webp', fbWebp()))->assertRedirect(route('feedback.sent'));
    foreach (FeedbackReport::all() as $r) {
        $info = getimagesizefromstring(Storage::disk('local')->get($r->screenshot_path));
        expect($info[2])->toBe(IMAGETYPE_JPEG)->and(max($info[0], $info[1]))->toBeLessThanOrEqual(2560);
    }
    $first = getimagesizefromstring(Storage::disk('local')->get(FeedbackReport::orderBy('id')->first()->screenshot_path));
    expect($first[0])->toBe(2560)->and($first[1])->toBe(853);
});

test('a name does not make a file an image: HTML, SVG, PHP and GIF are refused and keep the text', function () {
    $cases = [
        'html as png' => fbFile('shot.png', '<html><script>alert(1)</script></html>'),
        'svg as png' => fbFile('shot.png', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>'),
        'svg as svg' => fbFile('shot.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'),
        'php as jpg' => fbFile('shot.jpg', '<?php system($_GET["c"]); ?>'),
        'gif' => fbFile('shot.gif', fbGif()),
        'gif as png' => fbFile('shot.png', fbGif()),
        'text' => fbFile('shot.png', 'just words'),
    ];
    foreach ($cases as $label => $file) {
        fbSend($this, ['message' => 'Typed text that must stay.'], $file)->assertSessionHasErrors(['image' => 'Use a PNG, JPEG or WebP image.'])
            ->assertSessionHasInput('message', 'Typed text that must stay.');
    }
    expect(FeedbackReport::count())->toBe(0)->and(Storage::disk('local')->allFiles())->toBe([]);
});

test('a broken picture, a huge claimed size and a file over 5 MB are refused', function () {
    // A real PNG header followed by nothing: it opens as a PNG by its header and cannot be decoded.
    $broken = substr(fbPng(), 0, 40);
    fbSend($this, [], fbFile('broken.png', $broken))->assertSessionHasErrors(['image' => 'That file could not be read as an image.']);
    // 8000 x 7000 = 56 million pixels, declared in a header of a few bytes: refused before anything is decoded.
    $ihdr = pack('N', 8000).pack('N', 7000)."\x08\x02\x00\x00\x00";
    $bomb = "\x89PNG\r\n\x1a\n".pack('N', 13).'IHDR'.$ihdr.pack('N', crc32('IHDR'.$ihdr));
    fbSend($this, [], fbFile('bomb.png', $bomb))->assertSessionHasErrors(['image' => 'That image is too large. Use one up to 5 MB.']);
    $big = fbJpeg().str_repeat("\0", 5 * 1024 * 1024 + 1);
    fbSend($this, [], fbFile('big.jpg', $big))->assertSessionHasErrors(['image' => 'That image is too large. Use one up to 5 MB.']);
    expect(FeedbackReport::count())->toBe(0)->and(Storage::disk('local')->allFiles())->toBe([]);
});

test('a file of exactly 5 MB is accepted', function () {
    $jpeg = fbJpeg();
    $exact = $jpeg.str_repeat("\0", 5 * 1024 * 1024 - strlen($jpeg));
    expect(strlen($exact))->toBe(5 * 1024 * 1024);
    fbSend($this, [], fbFile('exact.jpg', $exact))->assertRedirect(route('feedback.sent'));
    expect(FeedbackReport::sole()->screenshot_path)->not->toBeNull();
});

test('a file that the server could not receive is refused as too large', function () {
    $partial = new UploadedFile(tempnam(sys_get_temp_dir(), 'fb'), 'x.png', 'image/png', UPLOAD_ERR_INI_SIZE, true);
    fbSend($this, [], $partial)->assertSessionHasErrors(['image' => 'That image is too large. Use one up to 5 MB.']);
    expect(FeedbackReport::count())->toBe(0);
});

test('a second file field or an array of files is not a screenshot', function () {
    $this->post('/feedback', ['kind' => 'problem', 'message' => 'Two files sent at once, not allowed.', 'image' => [fbFile('a.png', fbPng()), fbFile('b.png', fbPng())]])
        ->assertSessionHasErrors('image');
    expect(FeedbackReport::count())->toBe(0)->and(Storage::disk('local')->allFiles())->toBe([]);
});

test('when the screenshot folder is full the report is kept without its image and the thank-you page says so', function () {
    config(['hop.feedback.max_folder_mb' => 0]);
    fbSend($this, [], fbFile('s.png', fbPng()))->assertRedirect(route('feedback.sent'));
    expect(FeedbackReport::sole()->screenshot_path)->toBeNull()->and(Storage::disk('local')->allFiles())->toBe([]);
    $this->withSession(['feedback.sent' => ['app' => 'hub', 'no_room' => true]])->withoutVite()->get('/feedback/sent')->assertOk()->assertSee('could not be kept because the space for screenshots is full');
    // With room again, the image is kept.
    config(['hop.feedback.max_folder_mb' => 1024]);
    fbSend($this, ['message' => 'This time there is room for it.'], fbFile('s.png', fbPng()));
    expect(FeedbackReport::orderByDesc('id')->first()->screenshot_path)->not->toBeNull();
    $this->withSession(['feedback.sent' => ['app' => 'hub', 'no_room' => false]])->withoutVite()->get('/feedback/sent')->assertOk()->assertDontSee('space for screenshots is full');
});

test('a stored screenshot has no address on the web', function () {
    fbSend($this, [], fbFile('s.png', fbPng()));
    $path = FeedbackReport::sole()->screenshot_path;
    expect(Storage::disk('public')->exists($path))->toBeFalse();
    foreach (['/storage/'.$path, '/'.$path, '/storage/private/'.$path, '/feedback/'.basename($path), '/feedback/1/screenshot', '/api/hop/feedback/1/screenshot'] as $url) {
        expect($this->get($url)->status())->toBeIn([401, 403, 404, 405]);
    }
    // The only route that can return the bytes is the signed one for Imperium.
    $serving = collect(Route::getRoutes()->getRoutes())->filter(fn ($r) => str_contains($r->uri(), 'screenshot'))->map->uri()->values()->all();
    expect($serving)->toBe(['api/hop/feedback/{id}/screenshot']);
    expect($this->actingAs(User::factory()->create())->get('/api/hop/feedback/1/screenshot')->status())->toBeIn([401, 404]);
});

test('nobody sees a report through the web, not even the one who sent it', function () {
    $user = User::factory()->create();
    fbSend($this->actingAs($user));
    foreach (['/feedback/'.FeedbackReport::sole()->id, '/feedback/list', '/feedback/mine', '/admin/feedback'] as $url) {
        $this->actingAs($user)->get($url)->assertNotFound();
    }
    expect($this->post('/feedback/1')->status())->toBeIn([404, 405]);
    $this->delete('/feedback')->assertStatus(405);
});

// ---- search engines ----------------------------------------------------------

test('the form is not in the sitemap or in the site text for search engines', function () {
    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();
    expect($xml)->not->toContain('feedback');
    expect(file_get_contents(app_path('Http/Controllers/SitemapController.php')))->not->toContain('feedback');
});

// ---- two hosts ---------------------------------------------------------------

describe('on two hosts', function () {
    beforeEach(function () {
        config()->set('hop.public_host', 'hel.test');
        config()->set('hop.hub_host', 'hub.hel.test');
        config()->set('hop.hub_url', 'https://hub.hel.test');
        config()->set('hop.public_url', 'https://hel.test');
        $this->app['router']->setRoutes(new RouteCollection);
        Route::middleware('web')->group(base_path('routes/web.php'));
        $this->app['router']->getRoutes()->refreshNameLookups();
    });

    test('the form answers on the hub host and the public host sends visitors there with the same query', function () {
        $this->withoutVite()->get('https://hub.hel.test/feedback?app=pixelhop&from=view&v=3.14.0')->assertOk()->assertSee('PixelHop · view')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('https://hel.test/feedback?app=pixelhop&from=view&v=3.14.0')->assertRedirect('https://hub.hel.test/feedback?app=pixelhop&from=view&v=3.14.0')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('https://hel.test/feedback')->assertRedirect('https://hub.hel.test/feedback');
        $this->get('https://hel.test/feedback/sent')->assertRedirect('https://hub.hel.test/feedback/sent');
    });

    test('a signed-in member is recognised on the hub host, where the sign-in lives', function () {
        $user = User::factory()->create(['username' => 'helena']);
        $this->actingAs($user)->withoutVite()->get('https://hub.hel.test/feedback')->assertOk()->assertSee('Sending as @helena');
        $this->actingAs($user)->post('https://hub.hel.test/feedback', ['kind' => 'problem', 'message' => 'Sent from the hub host as a member.', 'app' => 'hub'])
            ->assertRedirect('https://hub.hel.test/feedback/sent');
        expect(FeedbackReport::sole()->username)->toBe('helena');
    });

    test('with the switch off the public host does not send visitors on either', function () {
        config(['hop.feedback.enabled' => false]);
        $this->get('https://hel.test/feedback')->assertNotFound();
        $this->get('https://hel.test/feedback/sent')->assertNotFound();
    });

    test('the links on the public host go to the form on the hub host', function () {
        $html = $this->withoutVite()->get('https://hel.test/help')->assertOk()->getContent();
        expect($html)->toContain('https://hub.hel.test/feedback?app=hub&amp;from=help');
        $this->withoutVite()->get('https://hel.test/contact')->assertOk()->assertSee('Send feedback');
    });
});

test('a suspended member is signed out and nothing is saved', function () {
    $user = User::factory()->create(['status' => User::STATUS_SUSPENDED]);
    fbSend($this->actingAs($user))->assertRedirect(route('suspended'));
    expect(FeedbackReport::count())->toBe(0);
    $this->assertGuest();
});

test('a form sent with an expired page token goes back with the typed text and a notice', function () {
    app()->detectEnvironment(fn () => 'production'); // outside "testing" the CSRF check is on
    $this->from('/feedback?app=pixelhop')->post('/feedback', ['kind' => 'problem', 'message' => 'Typed before the session ended.', 'app' => 'pixelhop'])
        ->assertRedirect('/feedback?app=pixelhop')->assertSessionHas('session_expired')->assertSessionHasInput('message', 'Typed before the session ended.');
    expect(FeedbackReport::count())->toBe(0);
    app()->detectEnvironment(fn () => 'testing');
});

// ---- text that is not text, and a save that fails -----------------------------

test('a message that is not valid UTF-8 is refused before any work, with an ordinary message', function () {
    $r = fbSend($this, ['message' => "\xFF\xFEaaaaaaaaaaaaaaaa"], fbFile('s.png', fbPng()));
    $r->assertSessionHasErrors(['message' => 'That text could not be read. Remove unusual characters and try again.']);
    expect(FeedbackReport::count())->toBe(0)->and(Storage::disk('local')->allFiles())->toBe([])
        ->and(RateLimiter::attempts('feedback:g:127.0.0.1:h'))->toBe(0); // a typing mistake is free
});

test('control characters are dropped from the text, new lines and tabs stay', function () {
    fbSend($this, ['message' => "Line one\x00 with a NUL\x07 and a bell\n\tindented line two"]);
    expect(FeedbackReport::sole()->message)->toBe("Line one with a NUL and a bell\n\tindented line two");
});

test('a User-Agent that is not valid UTF-8 is cleaned and the report is still saved', function () {
    $this->withHeader('User-Agent', "Mozilla/5.0 \xFF\xFE broken")->post('/feedback', ['kind' => 'problem', 'message' => 'A report from a browser with a broken name.'])
        ->assertRedirect(route('feedback.sent'));
    $ua = FeedbackReport::sole()->user_agent;
    expect(mb_check_encoding($ua, 'UTF-8'))->toBeTrue()->and($ua)->toStartWith('Mozilla/5.0 ');
});

test('when saving fails the picture is removed, nothing of the report reaches the log, and a retry works', function () {
    Log::spy();
    $fail = true;
    FeedbackReport::creating(function () use (&$fail) {
        if ($fail) {
            throw new RuntimeException('SQLSTATE secret-text-of-the-report 203.0.113.9 helena');
        }
    });
    $r = fbSend($this, ['message' => 'secret-text-of-the-report, long enough to send.'], fbFile('s.png', fbPng()));
    $r->assertSessionHasErrors(['limit' => 'Your report could not be saved. Please try again.'])->assertSessionHasInput('message');
    expect($r->status())->toBe(302)->and(FeedbackReport::count())->toBe(0)->and(Storage::disk('local')->allFiles())->toBe([]);
    Log::shouldHaveReceived('warning')->withArgs(function ($message, $context = []) {
        return $message === 'A feedback report could not be saved' && ! str_contains(json_encode($context), 'secret') && ! str_contains(json_encode($context), '203.0.113.9');
    })->once();
    // The same text again is not mistaken for a double send.
    $fail = false;
    fbSend($this, ['message' => 'secret-text-of-the-report, long enough to send.'], fbFile('s.png', fbPng()))->assertRedirect(route('feedback.sent'));
    expect(FeedbackReport::count())->toBe(1)->and(Storage::disk('local')->allFiles('feedback'))->toHaveCount(1);
});

// ---- the limits count attempts ------------------------------------------------

test('failed attempts count too: three failed security checks use up the hour of a guest', function () {
    config(['services.turnstile.secret' => 'secret']);
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);
    foreach ([1, 2, 3] as $i) {
        fbSend($this, ['message' => "Attempt $i with a security check that fails.", 'cf-turnstile-response' => 'bad'], null, false)->assertSessionHasErrors('security');
    }
    fbSend($this, ['message' => 'Attempt 4, refused before the check.', 'cf-turnstile-response' => 'bad'], null, false)->assertStatus(429);
    Http::assertSentCount(3); // the fourth never reached Cloudflare
});

test('a refused picture counts as an attempt for a member', function () {
    $user = User::factory()->create();
    foreach (range(1, 10) as $i) {
        fbSend($this->actingAs($user), ['message' => "Attempt $i with something that is not a picture."], fbFile('x.png', 'not a picture'))->assertSessionHasErrors('image');
    }
    fbSend($this->actingAs($user), ['message' => 'The eleventh attempt, refused as too many.'])->assertStatus(429);
});

test('an IPv6 guest is counted per /64, not per address', function () {
    foreach (['2001:db8:1:2::1', '2001:db8:1:2:ffff::9', '2001:db8:1:2:1:2:3:4'] as $i => $ip) {
        $this->withServerVariables(['REMOTE_ADDR' => $ip])->post('/feedback', ['kind' => 'idea', 'message' => "Report $i from the same /64 network."])->assertRedirect(route('feedback.sent'));
    }
    $this->withServerVariables(['REMOTE_ADDR' => '2001:db8:1:2:aaaa::7'])->post('/feedback', ['kind' => 'idea', 'message' => 'The fourth, from the same network.'])->assertStatus(429);
    $this->withServerVariables(['REMOTE_ADDR' => '2001:db8:1:3::1'])->post('/feedback', ['kind' => 'idea', 'message' => 'Another /64 has its own count.'])->assertRedirect(route('feedback.sent'));
    expect(FeedbackController::addressKey('203.0.113.9'))->toBe('203.0.113.9')
        ->and(FeedbackController::addressKey('2001:db8:1:2::1'))->toBe(FeedbackController::addressKey('2001:db8:1:2:ffff::9'))
        ->and(FeedbackController::addressKey('2001:db8:1:2::1'))->not->toBe(FeedbackController::addressKey('2001:db8:1:3::1'));
});

// ---- decoding: the size of what the picture claims, memory, one at a time ------

/** A PNG that is only a header: it says how large the picture is, and cannot be decoded. */
function fbPngHeader(int $w, int $h, int $depth = 8, int $color = 2): string
{
    $ihdr = pack('N', $w).pack('N', $h).chr($depth).chr($color)."\x00\x00\x00";

    return "\x89PNG\r\n\x1a\n".pack('N', 13).'IHDR'.$ihdr.pack('N', crc32('IHDR'.$ihdr));
}

test('what a picture claims is limited to 25 million pixels in every format, before anything is decoded', function () {
    $webp = 'RIFF'.pack('V', 22).'WEBPVP8X'.pack('V', 10)."\x00\x00\x00\x00".substr(pack('V', 5999), 0, 3).substr(pack('V', 5999), 0, 3); // 6000 x 6000
    $jpeg = "\xFF\xD8\xFF\xC0".pack('n', 17)."\x08".pack('n', 5000).pack('n', 6000)."\x03\x01\x22\x00\x02\x11\x01\x03\x11\x01\xFF\xD9"; // 6000 x 5000
    foreach (['png 39 MP' => ['a.png', fbPngHeader(6500, 6000)], 'png 16-bit 39 MP' => ['a.png', fbPngHeader(6500, 6000, 16, 6)], 'webp 36 MP' => ['a.webp', $webp], 'jpeg 30 MP' => ['a.jpg', $jpeg]] as $label => [$name, $bytes]) {
        expect(getimagesizefromstring($bytes))->not->toBeFalse("$label is a header PHP reads");
        fbSend($this, ['message' => "A picture that claims too much ($label)."], fbFile($name, $bytes))
            ->assertSessionHasErrors(['image' => 'That image is too large. Use one up to 5 MB.']);
    }
    expect(FeedbackReport::count())->toBe(0)->and(Storage::disk('local')->allFiles())->toBe([]);
    // A 5K screenshot (14.7 million) is still welcome: its header passes the size check and only then fails to decode.
    fbSend($this, ['message' => 'A 5K screenshot header, which is allowed in.'], fbFile('a.png', fbPngHeader(5120, 2880)))
        ->assertSessionHasErrors(['image' => 'That file could not be read as an image.']);
});

test('the memory guard refuses a picture that would not fit before it is opened, and lets it in when there is room', function () {
    $old = ini_get('memory_limit');
    try {
        $file = fn () => fbFile('a.png', fbPngHeader(6000, 4000)); // 24 million pixels: 288 MB + 32 MB by the guard's count
        ini_set('memory_limit', (string) (memory_get_usage() + 250 * 1048576)); // 250 MB of room: less than the 320 MB the guard asks for
        fbSend($this, ['message' => 'Picture too big for the memory left.'], $file())->assertSessionHasErrors(['image' => 'That image is too large. Use one up to 5 MB.']);
        ini_set('memory_limit', '2G');
        fbSend($this, ['message' => 'Picture with plenty of memory left.'], $file())->assertSessionHasErrors(['image' => 'That file could not be read as an image.']);
    } finally {
        ini_set('memory_limit', $old);
    }
    expect(FeedbackReport::count())->toBe(0);
});

test('pictures are drawn again one at a time: whoever does not get the turn is asked to try again', function () {
    config(['hop.feedback.decode_wait_seconds' => 1]);
    $lock = Cache::lock('feedback:decode', 60);
    expect($lock->get())->toBeTrue();
    fbSend($this, ['message' => 'Sent while another picture is being drawn.'], fbFile('s.png', fbPng()))
        ->assertSessionHasErrors(['image' => 'The server is busy with another picture. Please try again in a moment.'])->assertSessionHasInput('message');
    expect(FeedbackReport::count())->toBe(0);
    $lock->release();
    fbSend($this, ['message' => 'Sent when the turn is free again.'], fbFile('s.png', fbPng()))->assertRedirect(route('feedback.sent'));
    // The turn is given back also when drawing fails.
    fbSend($this, ['message' => 'A broken picture gives the turn back.'], fbFile('b.png', substr(fbPng(), 0, 40)))->assertSessionHasErrors('image');
    expect(Cache::lock('feedback:decode', 5)->get())->toBeTrue();
});

test('a 16-bit PNG, a turned phone photo and a big WebP come out as ordinary JPEG pictures', function () {
    // 8 x 8, 16 bits per channel, with transparency
    $rows = '';
    for ($y = 0; $y < 8; $y++) {
        $rows .= "\x00".str_repeat("\x40\x00\x80\x00\xC0\x00\xFF\xFF", 8);
    }
    $ihdr = pack('N', 8).pack('N', 8)."\x10\x06\x00\x00\x00";
    $idat = gzcompress($rows);
    $png16 = "\x89PNG\r\n\x1a\n".pack('N', 13).'IHDR'.$ihdr.pack('N', crc32('IHDR'.$ihdr)).pack('N', strlen($idat)).'IDAT'.$idat.pack('N', crc32('IDAT'.$idat)).pack('N', 0).'IEND'.pack('N', crc32('IEND'));
    fbSend($this, ['message' => 'A sixteen bit picture with transparency.'], fbFile('deep.png', $png16))->assertRedirect(route('feedback.sent'));
    $im = fbJpeg(200, 100);
    $tiff = "II*\x00\x08\x00\x00\x00\x01\x00\x12\x01\x03\x00\x01\x00\x00\x00\x06\x00\x00\x00\x00\x00\x00\x00"; // Orientation = 6 (turn right)
    $payload = "Exif\x00\x00".$tiff;
    $turned = substr($im, 0, 2)."\xFF\xE1".pack('n', strlen($payload) + 2).$payload.substr($im, 2);
    fbSend($this, ['message' => 'A photo that says it must be turned.'], fbFile('turned.jpg', $turned))->assertRedirect(route('feedback.sent'));
    $big = imagecreatetruecolor(4000, 3000);
    ob_start();
    imagewebp($big, null, 40);
    fbSend($this, ['message' => 'A big WebP picture, flat colour.'], fbFile('big.webp', (string) ob_get_clean()))->assertRedirect(route('feedback.sent'));
    $reports = FeedbackReport::orderBy('id')->get();
    expect($reports)->toHaveCount(3);
    foreach ($reports as $r) {
        expect(getimagesizefromstring(Storage::disk('local')->get($r->screenshot_path))[2])->toBe(IMAGETYPE_JPEG);
    }
    if (function_exists('exif_read_data')) {
        $size = getimagesizefromstring(Storage::disk('local')->get($reports[1]->screenshot_path));
        expect([$size[0], $size[1]])->toBe([100, 200]); // turned upright
    }
    $size = getimagesizefromstring(Storage::disk('local')->get($reports[2]->screenshot_path));
    expect(max($size[0], $size[1]))->toBe(2560);
});

test('a picture with a script appended to it comes out as a plain picture', function () {
    $polyglot = fbPng().'<?php system($_GET["c"]); ?><script>alert(1)</script>';
    fbSend($this, [], fbFile('poly.png', $polyglot))->assertRedirect(route('feedback.sent'));
    $bytes = Storage::disk('local')->get(FeedbackReport::sole()->screenshot_path);
    expect($bytes)->not->toContain('<?php')->not->toContain('<script>')->not->toContain('PNG');
});
