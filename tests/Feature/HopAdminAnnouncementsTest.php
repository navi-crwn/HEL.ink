<?php

use App\Jobs\SendHopEvent;
use App\Models\Announcement;
use App\Models\ApiToken;
use App\Models\HopApp;
use App\Models\HopAudit;
use App\Models\LoginHistory;
use App\Models\Role;
use App\Models\User;
use App\Support\HopSignature;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/*
 * The admin API for announcements (Imperium) and the role rules of the user actions: signed, capability,
 * contract, audit.
 */

const ADM_SECRET = 'local-test-imperium-secret-long-enough';

function admApp(array $caps = ['admin_client'], string $slug = 'imperium'): void
{
    HopApp::create(['slug' => $slug, 'name' => ucfirst($slug), 'url' => 'https://'.$slug.'.example.test',
        'callback_path' => '/auth/sso/callback', 'start_path' => '/login', 'status' => 'active',
        'secret' => ADM_SECRET, 'capabilities' => $caps]);
}

/** A signed call as `$app`; `$actor` is the X-HOP-Actor header. */
function adm($test, string $method, string $path, ?array $body = null, string $actor = 'system', string $app = 'imperium')
{
    $raw = $body === null ? '' : json_encode($body);
    $b64 = fn ($s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    $ts = (string) time();
    $nonce = $b64(random_bytes(16));
    $sig = $b64(hash_hmac('sha256', HopSignature::stringToSign($app, $ts, $nonce, $method, $path, $raw), ADM_SECRET, true));

    return $test->call($method, $path, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_HOP_APP' => $app, 'HTTP_X_HOP_TIMESTAMP' => $ts, 'HTTP_X_HOP_NONCE' => $nonce,
        'HTTP_X_HOP_SIGNATURE' => $sig, 'HTTP_X_HOP_ACTOR' => $actor], $raw);
}

function admOwner(string $name = 'boss'): User
{
    $u = User::factory()->create(['username' => $name]);
    $u->roles()->create(['role' => Role::OWNER, 'app_slug' => null]);

    return $u;
}

function admBody(array $over = []): array
{
    return array_merge(['title' => 'Maintenance', 'body' => "Line one\nLine two", 'level' => 'warning', 'app_slug' => null,
        'published_at' => null, 'expires_at' => null, 'publish_now' => false, 'reason' => 'planned work'], $over);
}

function ann(array $over = []): Announcement
{
    return Announcement::create($over + ['title' => 'Notice', 'body' => 'Body', 'level' => 'info']);
}

beforeEach(function () {
    admApp();
});

// ---- access -----------------------------------------------------------------

test('every announcement call needs a signature and the admin_client capability', function () {
    $a = ann();
    foreach ([['GET', '/api/hop/announcements'], ['GET', '/api/hop/announcements/'.$a->id], ['POST', '/api/hop/announcements'],
        ['PUT', '/api/hop/announcements/'.$a->id], ['POST', '/api/hop/announcements/'.$a->id.'/actions']] as [$m, $p]) {
        $r = $this->json($m, $p);
        expect($r->status())->toBeIn([401, 404])->and($r->json('ok'))->toBe(false);
    }
    admApp(['account_api'], 'plain');
    foreach ([['GET', '/api/hop/announcements'], ['POST', '/api/hop/announcements']] as [$m, $p]) {
        adm($this, $m, $p, $m === 'POST' ? admBody() : null, 'system', 'plain')->assertForbidden()->assertJsonPath('ok', false);
    }
    expect(Announcement::count())->toBe(1);
});

// ---- create / show ------------------------------------------------------------

test('create makes a draft with the actor as author and writes an audit row', function () {
    $owner = admOwner();

    $res = adm($this, 'POST', '/api/hop/announcements', admBody(['app_slug' => 'pixelhop']), $owner->id.':boss')->assertCreated();

    $row = $res->json('data');
    expect($row)->toMatchArray(['title' => 'Maintenance', 'body' => "Line one\nLine two", 'level' => 'warning', 'app_slug' => 'pixelhop',
        'state' => 'draft', 'published_at' => null, 'expires_at' => null, 'author' => $owner->id.':boss'])
        ->and($row['id'])->toBeString()
        ->and($row['created_at'])->toMatch('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/');
    $audit = HopAudit::where('action', 'announcement.create')->first();
    expect($audit->actor)->toBe($owner->id.':boss')->and($audit->app_slug)->toBe('imperium')
        ->and($audit->target_type)->toBe('announcement')->and($audit->target_id)->toBe($row['id'])
        ->and($audit->reason)->toBe('planned work')->and($audit->params['title'])->toBe('Maintenance');
    adm($this, 'GET', '/api/hop/announcements/'.$row['id'])->assertOk()->assertJsonPath('data.id', $row['id']);
});

test('create with a time in the future is scheduled, publish_now wins over published_at, a system actor leaves author as the actor string', function () {
    $future = now()->addDays(2)->utc()->format('Y-m-d\TH:i:s\Z');
    $res = adm($this, 'POST', '/api/hop/announcements', admBody(['published_at' => $future, 'expires_at' => now()->addDays(3)->utc()->format('Y-m-d\TH:i:s\Z')]))->assertCreated();
    expect($res->json('data.state'))->toBe('scheduled')->and($res->json('data.published_at'))->toBe($future)->and($res->json('data.author'))->toBe('system');

    $now = adm($this, 'POST', '/api/hop/announcements', admBody(['published_at' => $future, 'publish_now' => true]))->assertCreated();
    expect($now->json('data.state'))->toBe('live');
});

test('create refuses bad input with the first problem named', function (array $over, string $message) {
    adm($this, 'POST', '/api/hop/announcements', admBody($over))->assertStatus(422)
        ->assertJson(['ok' => false, 'error' => 'bad_params', 'message' => $message]);
    expect(Announcement::count())->toBe(0);
})->with([
    'no title' => [['title' => ''], 'title is required'],
    'long title' => [['title' => str_repeat('a', 161)], 'title may not be longer than 160 characters'],
    'no body' => [['body' => ' '], 'body is required'],
    'long body' => [['body' => str_repeat('a', 5001)], 'body may not be longer than 5000 characters'],
    'bad level' => [['level' => 'loud'], 'level must be one of info, warning, critical'],
    'bad app' => [['app_slug' => 'nope'], 'app_slug must be empty or the slug of a registered app'],
    'bad published' => [['published_at' => 'tomorrow'], 'published_at must be an ISO 8601 date and time'],
    'bad expires' => [['published_at' => '2026-12-01T09:30:00Z', 'expires_at' => '12/02/2026'], 'expires_at must be an ISO 8601 date and time'],
    'expires before published' => [['published_at' => '2026-12-02T09:30:00Z', 'expires_at' => '2026-12-01T09:30:00Z'], 'expires_at must be after published_at'],
    'expires without published' => [['expires_at' => '2026-12-01T09:30:00Z'], 'expires_at must be after published_at'],
]);

test('show answers 404 not_found for an unknown id', function () {
    adm($this, 'GET', '/api/hop/announcements/999')->assertNotFound()
        ->assertJson(['ok' => false, 'error' => 'not_found', 'message' => 'No such announcement.']);
});

// ---- list ---------------------------------------------------------------------

test('list is newest first, pages by cursor, filters by state and level, and counts the matches', function () {
    $draft = ann(['published_at' => null, 'level' => 'info']);
    $scheduled = ann(['published_at' => now()->addDay(), 'level' => 'info']);
    $live = ann(['published_at' => now()->subDay(), 'expires_at' => null, 'level' => 'critical']);
    $expired = ann(['published_at' => now()->subDays(3), 'expires_at' => now()->subDay(), 'level' => 'info']);

    $all = adm($this, 'GET', '/api/hop/announcements')->assertOk();
    expect(array_column($all->json('data'), 'id'))->toBe([(string) $expired->id, (string) $live->id, (string) $scheduled->id, (string) $draft->id])
        ->and($all->json('page'))->toBe(['next' => null, 'total' => 4]);

    $p1 = adm($this, 'GET', '/api/hop/announcements?limit=2')->json();
    expect($p1['page'])->toBe(['next' => (string) $live->id, 'total' => 4]);
    $p2 = adm($this, 'GET', '/api/hop/announcements?limit=2&cursor='.$p1['page']['next'])->json();
    expect(array_column($p2['data'], 'id'))->toBe([(string) $scheduled->id, (string) $draft->id])->and($p2['page']['total'])->toBe(4);

    foreach (['draft' => $draft, 'scheduled' => $scheduled, 'live' => $live, 'expired' => $expired] as $state => $model) {
        $r = adm($this, 'GET', '/api/hop/announcements?state='.$state)->json();
        expect(array_column($r['data'], 'id'))->toBe([(string) $model->id])->and($r['data'][0]['state'])->toBe($state);
    }
    expect(adm($this, 'GET', '/api/hop/announcements?level=critical')->json('page.total'))->toBe(1)
        ->and(adm($this, 'GET', '/api/hop/announcements?state=bogus&level=bogus')->json('page.total'))->toBe(4)
        ->and(adm($this, 'GET', '/api/hop/announcements?limit=1000')->json('data'))->toHaveCount(4);
});

// ---- update -------------------------------------------------------------------

test('update replaces every editable field, a missing field clears it, and it is audited', function () {
    $a = ann(['app_slug' => 'pixelhop', 'published_at' => now()->subDay(), 'expires_at' => now()->addDay()]);

    $res = adm($this, 'PUT', '/api/hop/announcements/'.$a->id, ['title' => 'New', 'body' => 'Changed', 'level' => 'critical', 'reason' => 'fix'], '1:x')->assertOk();

    expect($res->json('data'))->toMatchArray(['title' => 'New', 'level' => 'critical', 'app_slug' => null, 'published_at' => null, 'expires_at' => null, 'state' => 'draft']);
    $audit = HopAudit::where('action', 'announcement.update')->first();
    expect($audit->target_id)->toBe((string) $a->id)->and($audit->reason)->toBe('fix')->and($audit->params['title'])->toBe('New');
    adm($this, 'PUT', '/api/hop/announcements/'.$a->id, admBody(['title' => '']))->assertStatus(422);
    adm($this, 'PUT', '/api/hop/announcements/999', admBody())->assertNotFound();
});

// ---- actions ------------------------------------------------------------------

test('publish makes a draft or a scheduled announcement live, leaves a live one, refuses an expired one', function () {
    $draft = ann(['published_at' => null]);
    $scheduled = ann(['published_at' => now()->addDay()]);
    $live = ann(['published_at' => now()->subDay(), 'expires_at' => null]);
    $expired = ann(['published_at' => now()->subDays(2), 'expires_at' => now()->subDay()]);

    foreach ([$draft, $scheduled] as $a) {
        adm($this, 'POST', '/api/hop/announcements/'.$a->id.'/actions', ['action' => 'publish', 'reason' => 'go'])->assertOk()->assertJsonPath('data.state', 'live');
    }
    $before = $live->fresh()->published_at;
    adm($this, 'POST', '/api/hop/announcements/'.$live->id.'/actions', ['action' => 'publish'])->assertOk()->assertJsonPath('data.state', 'live');
    expect($live->fresh()->published_at->equalTo($before))->toBeTrue();
    adm($this, 'POST', '/api/hop/announcements/'.$expired->id.'/actions', ['action' => 'publish'])->assertStatus(409)
        ->assertJson(['ok' => false, 'error' => 'expired', 'message' => 'This announcement has expired; change its expiry date first.']);
    expect(HopAudit::where('action', 'announcement.publish')->count())->toBe(2);
});

test('unpublish sends it back to a draft, whatever the state', function () {
    $live = ann(['published_at' => now()->subDay()]);
    $draft = ann(['published_at' => null]);

    adm($this, 'POST', '/api/hop/announcements/'.$live->id.'/actions', ['action' => 'unpublish'])->assertOk()
        ->assertJsonPath('data.state', 'draft')->assertJsonPath('data.published_at', null);
    adm($this, 'POST', '/api/hop/announcements/'.$draft->id.'/actions', ['action' => 'unpublish'])->assertOk()->assertJsonPath('data.state', 'draft');
    expect(HopAudit::where('action', 'announcement.unpublish')->count())->toBe(1);
});

test('delete removes the row and answers with it as it was', function () {
    $a = ann(['title' => 'Gone soon']);

    $res = adm($this, 'POST', '/api/hop/announcements/'.$a->id.'/actions', ['action' => 'delete', 'reason' => 'oops'])->assertOk();

    expect($res->json('data'))->toMatchArray(['id' => (string) $a->id, 'title' => 'Gone soon', 'deleted' => true])
        ->and(Announcement::find($a->id))->toBeNull();
    $audit = HopAudit::where('action', 'announcement.delete')->first();
    expect($audit->params)->toBe(['title' => 'Gone soon'])->and($audit->target_id)->toBe((string) $a->id);
});

test('an unknown action is 422 and an unknown announcement is 404', function () {
    $a = ann();
    adm($this, 'POST', '/api/hop/announcements/'.$a->id.'/actions', ['action' => 'archive'])->assertStatus(422)
        ->assertJson(['error' => 'unknown_action', 'message' => 'action must be one of publish, unpublish, delete']);
    adm($this, 'POST', '/api/hop/announcements/999/actions', ['action' => 'publish'])->assertNotFound()->assertJsonPath('error', 'not_found');
});

test('announcement writes show in the audit feed, and the old admin page still works', function () {
    adm($this, 'POST', '/api/hop/announcements', admBody())->assertCreated();
    $feed = adm($this, 'GET', '/api/hop/audit')->assertOk();
    expect($feed->json('data.0.action'))->toBe('announcement.create')->and($feed->json('data.0.target_type'))->toBe('announcement');

    $admin = admOwner();
    $this->actingAs($admin)->withSession(['2fa_verified' => true])->withoutVite()->get('/admin/announcements')->assertOk()->assertSee('Maintenance');
});

// ---- role parity ----------------------------------------------------------------

test('set_roles refuses a list that grants owner', function () {
    $actor = admOwner();
    $target = User::factory()->create();

    adm($this, 'POST', '/api/hop/users/'.$target->id.'/actions', ['action' => 'set_roles', 'params' => ['roles' => ['owner', 'admin']]], (string) $actor->id)
        ->assertStatus(422)->assertJsonPath('error', 'bad_params');
    expect($target->fresh()->roles()->count())->toBe(0);

});

test('set_roles is only for an owner, found from X-HOP-Actor', function () {
    Queue::fake();
    $target = User::factory()->create();
    $admin = User::factory()->create();
    $admin->roles()->create(['role' => Role::ADMIN, 'app_slug' => null]);
    $body = ['action' => 'set_roles', 'params' => ['roles' => ['moderator']]];

    foreach (['system', (string) $admin->id, $admin->id.':someone', '999999', 'garbage'] as $actor) {
        adm($this, 'POST', '/api/hop/users/'.$target->id.'/actions', $body, $actor)->assertForbidden()->assertJsonPath('error', 'forbidden');
    }
    expect($target->fresh()->roleNames())->toBe([]);

    $owner = admOwner();
    adm($this, 'POST', '/api/hop/users/'.$target->id.'/actions', $body, $owner->id.':boss')->assertOk();
    expect($target->fresh()->roleNames())->toBe(['moderator']);
    expect($target->roles()->first()->granted_by)->toBe($owner->id);
});

test('an account cannot suspend or delete itself through the API', function () {
    $ops = admOwner('ops');
    $admin = User::factory()->create();
    $admin->roles()->create(['role' => Role::ADMIN, 'app_slug' => null]);

    foreach (['suspend', 'delete'] as $action) {
        adm($this, 'POST', '/api/hop/users/'.$admin->id.'/actions', ['action' => $action], $admin->id.':me')->assertForbidden()->assertJsonPath('error', 'forbidden');
    }
    expect($admin->fresh()->status)->toBe(User::STATUS_ACTIVE)->and(User::find($admin->id))->not->toBeNull();
    // Another actor may still suspend.
    adm($this, 'POST', '/api/hop/users/'.$admin->id.'/actions', ['action' => 'suspend'], $ops->id.':ops')->assertOk();
});

test('suspend also deletes the API tokens', function () {
    Queue::fake();
    $ops = admOwner('ops');
    $user = User::factory()->create();
    ApiToken::generate($user->id, 'one');
    ApiToken::generate($user->id, 'two');
    DB::table('sessions')->insert(['id' => 'sess-api-1', 'user_id' => $user->id, 'payload' => 'x', 'last_activity' => time()]);
    HopApp::factory()->payhop()->create(['capabilities' => ['events']]);

    $res = adm($this, 'POST', '/api/hop/users/'.$user->id.'/actions', ['action' => 'suspend', 'reason' => 'abuse'], $ops->id.':ops')->assertOk();

    expect($user->fresh()->status)->toBe(User::STATUS_SUSPENDED)->and($user->apiTokens()->count())->toBe(0)
        ->and(HopAudit::where('action', 'suspend')->first()->params)->toBe(['tokens_deleted' => 2, 'sessions_deleted' => 1]);
    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);
    Queue::assertPushed(SendHopEvent::class, fn ($j) => $j->type === 'session.revoked');
});

test('the user detail lists tokens by name and last use, never a value, and ten logins', function () {
    $user = User::factory()->create();
    $made = ApiToken::generate($user->id, 'CI token');
    $made['model']->forceFill(['last_used_at' => now()->subHour()])->save();
    for ($i = 0; $i < 12; $i++) {
        LoginHistory::create(['user_id' => $user->id, 'kind' => 'login', 'logged_in_at' => now()->subMinutes($i)]);
    }

    $res = adm($this, 'GET', '/api/hop/users/'.$user->id)->assertOk();

    expect($res->json('data.tokens'))->toHaveCount(1)
        ->and($res->json('data.tokens.0.name'))->toBe('CI token')
        ->and($res->json('data.tokens.0.last_used_at'))->not->toBeNull()
        ->and($res->json('data.counts.api_tokens'))->toBe(1)
        ->and($res->json('data.logins'))->toHaveCount(10);
    expect($res->getContent())->not->toContain($made['plain_token'])->not->toContain(hash('sha256', $made['plain_token']));
});

test('the other user actions and the list answer as before', function () {
    $user = User::factory()->create();
    adm($this, 'POST', '/api/hop/users/'.$user->id.'/actions', ['action' => 'note', 'params' => ['text' => 'hello']], 'system')->assertOk();
    adm($this, 'POST', '/api/hop/users/'.$user->id.'/actions', ['action' => 'revoke_tokens'], 'system')->assertOk();
    adm($this, 'POST', '/api/hop/users/'.$user->id.'/actions', ['action' => 'activate'], 'system')->assertOk()->assertJsonPath('data.status', 'active');
    adm($this, 'GET', '/api/hop/users')->assertOk()->assertJsonPath('page.total', 1);
    adm($this, 'POST', '/api/hop/users/'.$user->id.'/actions', ['action' => 'nope'], 'system')->assertStatus(422)->assertJsonPath('error', 'unknown_action');
});

// ---- the actor rule (fail closed) -------------------------------------------------

test('suspend, delete and set_roles need an actor that names an existing account', function (string $action, string $shape) {
    $owner = admOwner('chief');
    $target = User::factory()->create();
    $actor = str_replace('{id}', (string) $owner->id, $shape);
    $body = ['action' => $action, 'params' => ['roles' => ['moderator']]];

    adm($this, 'POST', '/api/hop/users/'.$target->id.'/actions', $body, $actor)->assertForbidden()->assertJsonPath('error', 'forbidden');

    expect(User::find($target->id))->not->toBeNull()
        ->and($target->fresh()->status)->toBe(User::STATUS_ACTIVE)->and($target->fresh()->roleNames())->toBe([])
        ->and(HopAudit::where('action', $action)->count())->toBe(0);
})->with(function () {
    foreach (['suspend', 'delete', 'set_roles'] as $action) {
        foreach (['system' => 'system', 'empty' => '', 'leading space' => ' {id}', 'trailing text' => '{id}abc', 'space before colon' => '{id} :x',
            'leading zero' => '0{id}', 'unknown id' => '999999', 'newline' => "{id}:a\nb", 'name only' => 'chief'] as $label => $shape) {
            yield "$action: $label" => [$action, $shape];
        }
    }
});

test('a well-formed actor that names an existing account is accepted, with or without a username', function () {
    $owner = admOwner('chief');
    foreach ([(string) $owner->id, $owner->id.':chief', $owner->id.':'] as $actor) {
        $target = User::factory()->create();
        adm($this, 'POST', '/api/hop/users/'.$target->id.'/actions', ['action' => 'suspend'], $actor)->assertOk();
    }
});

test('system may still call the actions that do not need a person', function () {
    $user = User::factory()->create();
    foreach ([['note', ['text' => 'x']], ['revoke_sessions', []], ['revoke_tokens', []], ['activate', []]] as [$action, $params]) {
        adm($this, 'POST', '/api/hop/users/'.$user->id.'/actions', ['action' => $action, 'params' => $params], 'system')->assertOk();
    }
});

test('suspend is one transaction: a failure leaves the account active, the tokens alive and no audit row', function () {
    $ops = admOwner('ops');
    $user = User::factory()->create();
    ApiToken::generate($user->id, 'keep me');
    User::updating(function () {
        throw new RuntimeException('forced failure');
    });

    adm($this, 'POST', '/api/hop/users/'.$user->id.'/actions', ['action' => 'suspend'], $ops->id.':ops')->assertStatus(500);

    expect($user->fresh()->status)->toBe(User::STATUS_ACTIVE)->and($user->apiTokens()->count())->toBe(1)
        ->and(HopAudit::where('action', 'suspend')->count())->toBe(0);
});

// ---- announcement dates ---------------------------------------------------------------

test('a date or time that does not exist is refused, and publish_now is applied before the expiry check', function () {
    adm($this, 'POST', '/api/hop/announcements', admBody(['published_at' => '2026-02-30T10:00:00Z']))->assertStatus(422)
        ->assertJsonPath('message', 'published_at must be an ISO 8601 date and time');
    adm($this, 'POST', '/api/hop/announcements', admBody(['published_at' => '2026-12-01T25:00:00Z']))->assertStatus(422);

    $res = adm($this, 'POST', '/api/hop/announcements', admBody(['publish_now' => true, 'expires_at' => now()->addDay()->utc()->format('Y-m-d\TH:i:s\Z')]))->assertCreated();
    expect($res->json('data.state'))->toBe('live');
    adm($this, 'POST', '/api/hop/announcements', admBody(['publish_now' => true, 'published_at' => '2020-01-01T00:00:00Z', 'expires_at' => '2020-06-01T00:00:00Z']))
        ->assertStatus(422)->assertJsonPath('message', 'expires_at must be after published_at');
});
