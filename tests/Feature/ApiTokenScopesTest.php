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
