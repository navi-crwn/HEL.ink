<?php

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Support\Facades\Route;

test('tokens are created with scopes at the hub and listed in settings', function () {
    $user = User::factory()->create();
    $res = $this->actingAs($user)->withSession(['2fa_verified' => true])
        ->postJson('/api-tokens', ['name' => 'CLI', 'scopes' => ['link:create', 'bio:read']]);
    $res->assertOk()->assertJsonPath('scopes', ['link:create', 'bio:read']);
    $token = ApiToken::findByPlainToken($res->json('token'));
    expect($token->can('link:create'))->toBeTrue()
        ->and($token->can('link:write'))->toBeFalse()
        ->and($token->user->is($user))->toBeTrue();
    $this->withoutVite()->actingAs($user)->withSession(['2fa_verified' => true])->get('/settings')->assertOk()->assertSee('link:create');
    $this->actingAs($user)->withSession(['2fa_verified' => true])->postJson('/api-tokens', ['name' => 'bad', 'scopes' => ['root:*']])->assertStatus(422);
});

test('a wildcard scope and an unscoped token cover everything in their prefix', function () {
    $user = User::factory()->create();
    $all = ApiToken::generate($user->id, 'all')['model'];
    $link = ApiToken::generate($user->id, 'links', scopes: ['link:*'])['model'];
    expect($all->can('bio:write'))->toBeTrue()->and($link->can('link:read'))->toBeTrue()->and($link->can('bio:read'))->toBeFalse();
});

test('the hop.token middleware validates shared tokens with a scope', function () {
    Route::middleware('hop.token:link:create')->get('/_test/api', fn () => response()->json(['user' => request()->attributes->get('api_user')->id]));
    $user = User::factory()->create();
    $plain = ApiToken::generate($user->id, 'x', scopes: ['link:read'])['plain_token'];
    $this->getJson('/_test/api')->assertStatus(401);
    $this->withHeader('Authorization', 'Bearer nope')->getJson('/_test/api')->assertStatus(401);
    $this->withHeader('Authorization', 'Bearer '.$plain)->getJson('/_test/api')->assertStatus(403);
    $ok = ApiToken::generate($user->id, 'y', scopes: ['link:*'])['plain_token'];
    $this->withHeader('Authorization', 'Bearer '.$ok)->getJson('/_test/api')->assertOk()->assertJsonPath('user', $user->id);
    $user->forceFill(['status' => User::STATUS_SUSPENDED])->save();
    $this->withHeader('Authorization', 'Bearer '.$ok)->getJson('/_test/api')->assertStatus(403);
});

function pixelAndLinkApps(): void
{
    config([
        'hop.apps.linkhop.url' => 'https://link.example.test',
        'hop.apps.pixelhop.url' => 'https://pixel.example.test',
    ]);
}

test('the token form offers the link scopes and no pixel scope, and sends people to PixelHop for its keys', function () {
    pixelAndLinkApps();
    $user = User::factory()->create();
    $html = $this->withoutVite()->actingAs($user)->withSession(['2fa_verified' => true])->get('/settings')->assertOk()->getContent();
    expect($html)->toContain('value="link:read"')->toContain('value="link:write"')
        ->and($html)->not->toContain('pixel:read')->not->toContain('pixel:write')
        ->and($html)->not->toContain('One token works at every HOP API')
        ->and($html)->toContain('Hub tokens (hlk_…) work at LinkHop')
        ->and($html)->toContain('href="https://pixel.example.test/settings#api"');
});

test('the API page says hub tokens are for LinkHop and PixelHop keys are made in PixelHop', function () {
    pixelAndLinkApps();
    $html = $this->withoutVite()->actingAs(User::factory()->create())->withSession(['2fa_verified' => true])
        ->get('/api-docs')->assertOk()->getContent();
    expect($html)->not->toContain('One token, every HOP API')->not->toContain('against the same table')
        ->and($html)->toContain('Hub tokens (hlk_…) are for LinkHop.')
        ->and($html)->toContain('Apps with their own keys')
        ->and($html)->toContain('href="https://pixel.example.test/settings#api"')
        ->and($html)->toContain('href="https://pixel.example.test/docs"')
        ->and($html)->not->toContain('pixel:read')->not->toContain('pixel:write');
});

test('a new token cannot carry a pixel scope, but one that already does keeps working and is listed', function () {
    pixelAndLinkApps();
    $user = User::factory()->create();
    $this->actingAs($user)->withSession(['2fa_verified' => true])
        ->postJson('/api-tokens', ['name' => 'stale form', 'scopes' => ['pixel:read']])->assertStatus(422);
    $this->actingAs($user)->withSession(['2fa_verified' => true])
        ->postJson('/api-tokens', ['name' => 'ok', 'scopes' => ['link:read']])->assertOk();

    // A token made before this change.
    $old = ApiToken::generate($user->id, 'old pixel token', scopes: ['link:read', 'pixel:write']);
    expect($old['model']->fresh()->can('pixel:write'))->toBeTrue()->and($old['model']->can('pixel:read'))->toBeFalse();
    Route::middleware('hop.token:pixel:write')->get('/_test/pixel', fn () => response()->json(['ok' => true]));
    $this->withHeader('Authorization', 'Bearer '.$old['plain_token'])->getJson('/_test/pixel')->assertOk();
    $this->withoutVite()->actingAs($user)->withSession(['2fa_verified' => true])->get('/settings')
        ->assertOk()->assertSee('old pixel token')->assertSee('Scopes: link:read, pixel:write');
});

test('the new API texts exist in all nine languages', function () {
    $keys = [
        'Hub tokens (hlk_…) are for :apps.',
        'Apps with their own keys',
        ':app has its own keys (:prefix…), made at :keys and documented at :docs. A hub token (hlk_…) is not accepted there.',
        'Hub tokens (hlk_…) work at :apps and are limited to the scopes you pick.',
        ':app keys (:prefix…) are made in :app itself, at :link, not here.',
    ];
    foreach (['zh-CN', 'de', 'id', 'ja', 'pt-BR', 'es', 'fr', 'ru'] as $locale) {
        $lang = json_decode(file_get_contents(lang_path($locale.'.json')), true);
        foreach ($keys as $key) {
            expect($lang[$key] ?? null)->not->toBeNull()->not->toBe($key);
            preg_match_all('/:[a-z]+/', $key, $want);
            preg_match_all('/:[a-z]+/', $lang[$key], $got);
            expect($got[0])->toEqualCanonicalizing($want[0]);
        }
    }
    foreach (['One token, every HOP API.', 'One token works at every HOP API (:apps) and is limited to the scopes you pick.'] as $gone) {
        foreach (glob(lang_path('*.json')) as $file) {
            expect(json_decode(file_get_contents($file), true))->not->toHaveKey($gone);
        }
    }
});
