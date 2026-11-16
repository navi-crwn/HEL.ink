<?php

use App\Models\FeedbackReport;
use App\Models\HopApp;
use App\Models\HopAudit;
use App\Models\User;
use App\Support\HopSignature;
use Illuminate\Support\Facades\Storage;

/*
 * /api/hop/feedback for Imperium: signed, admin_client only, the shape fixed in dokumen/42 F-4 (with both
 * "Penegasan"), every action, and the screenshot streamed from the private disk.
 */

const FBA_SECRET = 'local-test-imperium-secret-long-enough';

function fbaApp(array $caps = ['admin_client'], string $slug = 'imperium'): void
{
    HopApp::create(['slug' => $slug, 'name' => ucfirst($slug), 'url' => 'https://'.$slug.'.example.test',
        'callback_path' => '/auth/sso/callback', 'start_path' => '/login', 'status' => 'active',
        'secret' => FBA_SECRET, 'capabilities' => $caps, 'hidden' => $slug === 'imperium']);
}

/** A signed call as `$app`. */
function fba($test, string $method, string $path, ?array $body = null, string $app = 'imperium', string $actor = '7:boss')
{
    $raw = $body === null ? '' : json_encode($body);
    $b64 = fn ($s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    $ts = (string) time();
    $nonce = $b64(random_bytes(16));
    $sig = $b64(hash_hmac('sha256', HopSignature::stringToSign($app, $ts, $nonce, $method, $path, $raw), FBA_SECRET, true));

    return $test->call($method, $path, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_HOP_APP' => $app, 'HTTP_X_HOP_TIMESTAMP' => $ts, 'HTTP_X_HOP_NONCE' => $nonce,
        'HTTP_X_HOP_SIGNATURE' => $sig, 'HTTP_X_HOP_ACTOR' => $actor], $raw);
}

function fbaReport(array $over = []): FeedbackReport
{
    return FeedbackReport::create($over + ['kind' => 'problem', 'status' => 'new', 'app' => 'pixelhop', 'page' => 'view', 'app_version' => '3.14.0',
        'message' => 'The Save button does nothing.', 'ip_address' => '203.0.113.7', 'user_agent' => 'Mozilla/5.0', 'viewport' => '1440x900', 'locale' => 'en']);
}

function fbaShot(FeedbackReport $r): void
{
    $im = imagecreatetruecolor(20, 20);
    ob_start();
    imagejpeg($im);
    $path = 'feedback/2026/11/'.bin2hex(random_bytes(20)).'.jpg';
    Storage::disk('local')->put($path, (string) ob_get_clean());
    $r->update(['screenshot_path' => $path]);
}

beforeEach(function () {
    Storage::fake('local');
    fbaApp();
});

// ---- who may call ------------------------------------------------------------

test('every route refuses a call without a signature (401) and an app without admin_client (403)', function () {
    $r = fbaReport();
    fbaShot($r);
    $routes = [['GET', '/api/hop/feedback'], ['GET', '/api/hop/feedback/'.$r->id], ['GET', '/api/hop/feedback/'.$r->id.'/screenshot'],
        ['POST', '/api/hop/feedback/'.$r->id.'/actions']];
    fbaApp(['account_api'], 'pixelhop');
    foreach ($routes as [$method, $path]) {
        $none = $this->json($method, $path, $method === 'POST' ? ['action' => 'delete'] : []);
        // No signature headers at all: the shared signature check answers like it does for every /api/hop route
        // (not_configured, 404: it does not say what is behind the path); a signature that is wrong, old or reused is 401.
        expect($none->status())->toBeIn([401, 404], "$method $path without a signature");
        expect($none->json('ok'))->toBeFalse();
        // A wrong signature is no better.
        $bad = $this->call($method, $path, [], [], [], ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_HOP_APP' => 'imperium', 'HTTP_X_HOP_TIMESTAMP' => (string) time(),
            'HTTP_X_HOP_NONCE' => str_repeat('a', 22), 'HTTP_X_HOP_SIGNATURE' => 'not-a-signature']);
        expect($bad->status())->toBe(401);
        $plain = fba($this, $method, $path, $method === 'POST' ? ['action' => 'delete'] : null, 'pixelhop');
        expect($plain->status())->toBe(403, "$method $path as an app without admin_client");
        expect($plain->json('error'))->toBe('forbidden');
    }
    // Nothing happened to the report, and the image is still there.
    expect(FeedbackReport::count())->toBe(1)->and(Storage::disk('local')->exists($r->fresh()->screenshot_path))->toBeTrue();
    // A signed-in member of the web is not a signed call either.
    expect($this->actingAs(User::factory()->create())->json('GET', '/api/hop/feedback')->status())->toBeIn([401, 404]);
});

test('a signature cannot be replayed', function () {
    fbaReport();
    $path = '/api/hop/feedback';
    $b64 = fn ($s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    $ts = (string) time();
    $nonce = $b64(random_bytes(16));
    $sig = $b64(hash_hmac('sha256', HopSignature::stringToSign('imperium', $ts, $nonce, 'GET', $path, ''), FBA_SECRET, true));
    $headers = ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_HOP_APP' => 'imperium', 'HTTP_X_HOP_TIMESTAMP' => $ts, 'HTTP_X_HOP_NONCE' => $nonce, 'HTTP_X_HOP_SIGNATURE' => $sig];
    $this->call('GET', $path, [], [], [], $headers)->assertOk();
    $this->call('GET', $path, [], [], [], $headers)->assertStatus(401);
});

// ---- the list ----------------------------------------------------------------

test('the list answers newest first in exactly the shape of the contract', function () {
    fbaReport(['message' => "First\nreport"]);
    $mem = User::factory()->create(['username' => 'helena']);
    $second = fbaReport(['user_id' => $mem->id, 'username' => 'helena', 'kind' => 'idea', 'app' => 'hub', 'page' => null, 'message' => "  A   long\n\nmessage  ".str_repeat('x', 300)]);
    fbaShot($second);
    $r = fba($this, 'GET', '/api/hop/feedback');
    $r->assertOk();
    expect($r->json('ok'))->toBeTrue()->and($r->json('page'))->toBe(['next' => null, 'total' => 2]);
    $rows = $r->json('data');
    expect($rows)->toHaveCount(2)->and($rows[0]['id'])->toBe((string) $second->id);
    expect(array_keys($rows[0]))->toBe(['id', 'kind', 'status', 'app', 'page', 'excerpt', 'has_screenshot', 'username', 'created_at']);
    expect($rows[0])->toMatchArray(['kind' => 'idea', 'status' => 'new', 'app' => 'hub', 'page' => null, 'has_screenshot' => true, 'username' => 'helena']);
    expect(mb_strlen($rows[0]['excerpt']))->toBeLessThanOrEqual(160)->and($rows[0]['excerpt'])->toStartWith('A long message x')->and($rows[0]['excerpt'])->not->toContain("\n");
    expect($rows[0]['created_at'])->toMatch('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/');
    expect($rows[1])->toMatchArray(['username' => null, 'has_screenshot' => false, 'excerpt' => 'First report', 'page' => 'view']);
    expect($rows[1]['id'])->toBeString();
});

test('the list filters by app, kind and status, and page.total is the filtered count', function () {
    fbaReport(['app' => 'pixelhop']);
    fbaReport(['app' => 'pixelhop', 'status' => 'done', 'kind' => 'idea']);
    fbaReport(['app' => 'filehop']);
    expect(fba($this, 'GET', '/api/hop/feedback?app=pixelhop')->json('page.total'))->toBe(2);
    expect(fba($this, 'GET', '/api/hop/feedback?kind=idea')->json('page.total'))->toBe(1);
    expect(fba($this, 'GET', '/api/hop/feedback?status=new&limit=1')->json('page.total'))->toBe(2);
    expect(fba($this, 'GET', '/api/hop/feedback?status=new&app=filehop&limit=1')->json('page.total'))->toBe(1);
    $only = fba($this, 'GET', '/api/hop/feedback?app=filehop')->json('data');
    expect($only)->toHaveCount(1)->and($only[0]['app'])->toBe('filehop');
    // An empty value is "no filter", as Imperium sends it.
    expect(fba($this, 'GET', '/api/hop/feedback?app=&kind=&status=')->json('page.total'))->toBe(3);
});

test('bad filters are a 422, never a silently empty list', function () {
    fbaReport();
    foreach (['app=nope', 'kind=nope', 'status=nope', 'app[]=hub', 'limit=abc', 'limit=0', 'limit=-3', 'limit=1.5', 'cursor=a.b', 'cursor=x%20y', 'cursor=abc', 'cursor[]=1'] as $q) {
        $r = fba($this, 'GET', '/api/hop/feedback?'.$q);
        expect($r->status())->toBe(422, $q)->and($r->json('error'))->toBe('bad_params')->and($r->json('ok'))->toBeFalse();
    }
});

test('pages: limit 25 by default, 100 at most, and next is null when nothing follows', function () {
    foreach (range(1, 30) as $i) {
        fbaReport(['message' => "Report $i, written down."]);
    }
    $first = fba($this, 'GET', '/api/hop/feedback');
    expect($first->json('data'))->toHaveCount(25)->and($first->json('page.total'))->toBe(30)->and($first->json('page.next'))->toBe((string) FeedbackReport::orderByDesc('id')->skip(24)->first()->id);
    $second = fba($this, 'GET', '/api/hop/feedback?cursor='.$first->json('page.next'));
    expect($second->json('data'))->toHaveCount(5)->and($second->json('page.next'))->toBeNull();
    expect(collect($first->json('data'))->pluck('id')->intersect(collect($second->json('data'))->pluck('id')))->toHaveCount(0);
    // limit above 100 is cut to 100, not refused.
    foreach (range(31, 110) as $i) {
        fbaReport(['message' => "Report $i, written down."]);
    }
    expect(fba($this, 'GET', '/api/hop/feedback?limit=500')->json('data'))->toHaveCount(100);
    // A last page that fills the limit exactly does not point at an empty page.
    $exact = fba($this, 'GET', '/api/hop/feedback?limit=110');
    expect($exact->json('data'))->toHaveCount(100); // capped
    FeedbackReport::query()->where('id', '>', FeedbackReport::orderBy('id')->skip(9)->first()->id)->get()->each->delete();
    expect(FeedbackReport::count())->toBe(10);
    $all = fba($this, 'GET', '/api/hop/feedback?limit=10');
    expect($all->json('data'))->toHaveCount(10)->and($all->json('page.next'))->toBeNull();
    $some = fba($this, 'GET', '/api/hop/feedback?limit=9');
    expect($some->json('data'))->toHaveCount(9)->and($some->json('page.next'))->not->toBeNull();
    expect(fba($this, 'GET', '/api/hop/feedback?limit=9&cursor='.$some->json('page.next'))->json('page.next'))->toBeNull();
});

test('the list honours the app filter for admin:<app> (Imperium asks per app)', function () {
    fbaReport(['app' => 'payhop', 'message' => 'About PayHop, long enough.']);
    fbaReport(['app' => 'notehop', 'message' => 'About NoteHop, long enough.']);
    $rows = fba($this, 'GET', '/api/hop/feedback?app=payhop')->json('data');
    expect($rows)->toHaveCount(1)->and($rows[0]['app'])->toBe('payhop');
});

// ---- one report --------------------------------------------------------------

test('a report is returned in the contract shape, with nulls instead of empty strings', function () {
    $mem = User::factory()->create(['username' => 'helena']);
    $r = fbaReport(['user_id' => $mem->id, 'username' => 'helena', 'message' => "Line one\nLine two <b>x</b>"]);
    $d = fba($this, 'GET', '/api/hop/feedback/'.$r->id)->assertOk()->json('data');
    expect(array_keys($d))->toBe(['id', 'kind', 'status', 'app', 'page', 'app_version', 'message', 'user', 'ip', 'user_agent', 'viewport', 'locale',
        'has_screenshot', 'created_at', 'read_at', 'done_at']);
    expect($d)->toMatchArray(['id' => (string) $r->id, 'kind' => 'problem', 'status' => 'new', 'app' => 'pixelhop', 'page' => 'view', 'app_version' => '3.14.0',
        'message' => "Line one\nLine two <b>x</b>", 'ip' => '203.0.113.7', 'user_agent' => 'Mozilla/5.0', 'viewport' => '1440x900', 'locale' => 'en',
        'has_screenshot' => false, 'read_at' => null, 'done_at' => null]);
    expect($d['user'])->toBe(['id' => $mem->id, 'username' => 'helena']);
    // A guest, an emptied address and empty fields are null, never "".
    $g = fbaReport(['ip_address' => null, 'page' => '', 'app_version' => '', 'user_agent' => '', 'viewport' => '', 'locale' => '']);
    $d = fba($this, 'GET', '/api/hop/feedback/'.$g->id)->json('data');
    foreach (['user', 'ip', 'page', 'app_version', 'user_agent', 'viewport', 'locale', 'read_at', 'done_at'] as $key) {
        expect($d[$key])->toBeNull($key);
    }
    $row = fba($this, 'GET', '/api/hop/feedback?limit=1')->json('data.0');
    expect($row['page'])->toBeNull()->and($row['username'])->toBeNull();
});

test('every route with an id answers 404 not_found as JSON for an id that is not there', function () {
    $r = fbaReport();
    fbaShot($r);
    foreach (['999999', 'abc', '0', '-1', '1.5', '1e3', str_repeat('9', 40), 'a_b-c', '%20', '1%3Bdrop'] as $id) {
        foreach ([['GET', "/api/hop/feedback/$id"], ['GET', "/api/hop/feedback/$id/screenshot"], ['POST', "/api/hop/feedback/$id/actions"]] as [$m, $p]) {
            $res = fba($this, $m, $p, $m === 'POST' ? ['action' => 'read'] : null);
            expect($res->status())->toBe(404, "$m $p")->and($res->json('error'))->toBe('not_found', "$m $p")->and($res->json('ok'))->toBeFalse();
        }
    }
    expect($r->fresh()->status)->toBe('new');
});

// ---- the screenshot ----------------------------------------------------------

test('the screenshot is streamed as a JPEG with the contract headers; none means 404', function () {
    $r = fbaReport();
    fbaShot($r);
    $res = fba($this, 'GET', '/api/hop/feedback/'.$r->id.'/screenshot');
    $res->assertOk();
    expect($res->headers->get('Content-Type'))->toBe('image/jpeg')->and($res->headers->get('X-Content-Type-Options'))->toBe('nosniff');
    expect($res->headers->get('Cache-Control'))->toContain('no-store')->toContain('private');
    $bytes = $res->getContent();
    expect(substr($bytes, 0, 3))->toBe("\xFF\xD8\xFF")->and(strlen($bytes))->toBeLessThan(10 * 1024 * 1024);
    expect($bytes)->toBe(Storage::disk('local')->get($r->fresh()->screenshot_path));
    // Without an image: 404 not_found. With a row that points at a file that is gone: also 404.
    $none = fbaReport();
    fba($this, 'GET', '/api/hop/feedback/'.$none->id.'/screenshot')->assertNotFound()->assertJsonPath('error', 'not_found');
    Storage::disk('local')->delete($r->fresh()->screenshot_path);
    fba($this, 'GET', '/api/hop/feedback/'.$r->id.'/screenshot')->assertNotFound()->assertJsonPath('error', 'not_found');
});

// ---- actions -----------------------------------------------------------------

test('read, done and reopen change the status as the contract says and answer with the report', function () {
    $r = fbaReport();
    $url = '/api/hop/feedback/'.$r->id.'/actions';
    $d = fba($this, 'POST', $url, ['action' => 'read'])->assertOk()->json('data');
    expect($d['status'])->toBe('read')->and($d['read_at'])->not->toBeNull()->and($d['done_at'])->toBeNull();
    $readAt = $d['read_at'];
    // read on a report that is not new: 200, the report as it is, nothing changed.
    $this->travel(5)->minutes();
    $d = fba($this, 'POST', $url, ['action' => 'read'])->assertOk()->json('data');
    expect($d['status'])->toBe('read')->and($d['read_at'])->toBe($readAt);
    $d = fba($this, 'POST', $url, ['action' => 'done'])->assertOk()->json('data');
    expect($d['status'])->toBe('done')->and($d['done_at'])->not->toBeNull();
    $doneAt = $d['done_at'];
    $d = fba($this, 'POST', $url, ['action' => 'read'])->assertOk()->json('data');
    expect($d['status'])->toBe('done'); // read never moves a finished report back
    $d = fba($this, 'POST', $url, ['action' => 'done'])->json('data');
    expect($d['done_at'])->toBe($doneAt);
    $d = fba($this, 'POST', $url, ['action' => 'reopen'])->assertOk()->json('data');
    expect($d['status'])->toBe('read')->and($d['done_at'])->toBeNull();
    // A report that is new can be finished directly, and read_at is filled then.
    $n = fbaReport();
    $d = fba($this, 'POST', '/api/hop/feedback/'.$n->id.'/actions', ['action' => 'done'])->json('data');
    expect($d['status'])->toBe('done')->and($d['read_at'])->not->toBeNull();
});

test('delete removes the row and its image file and answers {ok:true}', function () {
    $r = fbaReport();
    fbaShot($r);
    $other = fbaReport();
    fbaShot($other);
    $path = $r->fresh()->screenshot_path;
    expect(Storage::disk('local')->exists($path))->toBeTrue();
    $res = fba($this, 'POST', '/api/hop/feedback/'.$r->id.'/actions', ['action' => 'delete'])->assertOk();
    expect($res->json())->toBe(['ok' => true]);
    expect(FeedbackReport::find($r->id))->toBeNull()->and(Storage::disk('local')->exists($path))->toBeFalse();
    expect(Storage::disk('local')->exists($other->fresh()->screenshot_path))->toBeTrue();
    fba($this, 'GET', '/api/hop/feedback/'.$r->id)->assertNotFound()->assertJsonPath('error', 'not_found');
    fba($this, 'POST', '/api/hop/feedback/'.$r->id.'/actions', ['action' => 'delete'])->assertNotFound();
});

test('another action is 400 bad_action, with or without a body, and changes nothing', function () {
    $r = fbaReport();
    $url = '/api/hop/feedback/'.$r->id.'/actions';
    foreach ([['action' => 'publish'], ['action' => ''], ['action' => ['read']], ['action' => 5], [], ['act' => 'read']] as $body) {
        $res = fba($this, 'POST', $url, $body);
        expect($res->status())->toBe(400)->and($res->json('error'))->toBe('bad_action');
    }
    expect(fba($this, 'POST', $url)->status())->toBe(400);
    expect($r->fresh()->status)->toBe('new');
});

test('changes are written to the hub audit with the actor and no text; opening a report is not', function () {
    $r = fbaReport(['message' => 'A secret story about the app that must stay out of the audit.']);
    $url = '/api/hop/feedback/'.$r->id.'/actions';
    fba($this, 'POST', $url, ['action' => 'read'], 'imperium', '7:boss');
    expect(HopAudit::count())->toBe(0);
    fba($this, 'POST', $url, ['action' => 'done'], 'imperium', '7:boss');
    fba($this, 'POST', $url, ['action' => 'reopen'], 'imperium', '8:mod');
    fba($this, 'POST', $url, ['action' => 'delete'], 'imperium', '7:boss');
    $rows = HopAudit::orderBy('id')->get();
    expect($rows->pluck('action')->all())->toBe(['feedback.done', 'feedback.reopen', 'feedback.delete'])
        ->and($rows->pluck('actor')->all())->toBe(['7:boss', '8:mod', '7:boss'])
        ->and($rows->pluck('target_id')->unique()->all())->toBe([(string) $r->id]);
    expect(json_encode($rows->toArray()))->not->toContain('secret story');
});

test('the API keeps answering when the form is switched off, so reports already kept can still be read', function () {
    fbaReport();
    config(['hop.feedback.enabled' => false]);
    fba($this, 'GET', '/api/hop/feedback')->assertOk()->assertJsonPath('page.total', 1);
});

test('a member without a username is null in the report, not an empty text', function () {
    $mem = User::factory()->create(['username' => null]);
    $r = fbaReport(['user_id' => $mem->id, 'username' => null]);
    $d = fba($this, 'GET', '/api/hop/feedback/'.$r->id)->assertOk()->json('data');
    expect($d['user'])->toBe(['id' => $mem->id, 'username' => null]);
    expect(fba($this, 'GET', '/api/hop/feedback')->json('data.0.username'))->toBeNull();
});

test('an actor header that is too long or not text is cut for the audit, and the change still stands', function () {
    $r = fbaReport();
    $url = '/api/hop/feedback/'.$r->id.'/actions';
    fba($this, 'POST', $url, ['action' => 'done'], 'imperium', '7:'.str_repeat('x', 200))->assertOk()->assertJsonPath('data.status', 'done');
    $row = HopAudit::sole();
    expect(mb_strlen($row->actor))->toBe(64)->and($r->fresh()->status)->toBe('done');
    fba($this, 'POST', $url, ['action' => 'reopen'], 'imperium', "9:\xFF\xFE bad bytes")->assertOk();
    expect(HopAudit::count())->toBe(2)->and(mb_check_encoding(HopAudit::orderByDesc('id')->first()->actor, 'UTF-8'))->toBeTrue();
});

test('a change and its audit row are one: done twice writes one row, and a failing audit undoes the change', function () {
    $r = fbaReport();
    $url = '/api/hop/feedback/'.$r->id.'/actions';
    fba($this, 'POST', $url, ['action' => 'done']);
    fba($this, 'POST', $url, ['action' => 'done']);
    expect(HopAudit::where('action', 'feedback.done')->count())->toBe(1);
    // The audit table refuses the row: the report is not left half changed.
    $r2 = fbaReport();
    HopAudit::creating(fn () => throw new RuntimeException('audit refused'));
    $res = fba($this, 'POST', '/api/hop/feedback/'.$r2->id.'/actions', ['action' => 'done']);
    expect($res->status())->toBe(500)->and($r2->fresh()->status)->toBe('new')->and($r2->fresh()->done_at)->toBeNull();
});
