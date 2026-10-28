<?php

use App\Models\User;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;

/** One app, two hosts: hel.ink (public) and hub.hel.ink (accounts). */
beforeEach(function () {
    config()->set('hop.public_host', 'hel.test');
    config()->set('hop.hub_host', 'hub.hel.test');
    config()->set('hop.hub_url', 'https://hub.hel.test');
    config()->set('hop.public_url', 'https://hel.test');
    // Routes were built with the test hosts (localhost); rebuild them for this test.
    $this->app['router']->setRoutes(new RouteCollection);
    Route::middleware('web')->group(base_path('routes/web.php'));
    $this->app['router']->getRoutes()->refreshNameLookups();
});

test('the public host serves the home page and marketing pages', function () {
    $this->withoutVite()->get('https://hel.test/')->assertOk()->assertSee('One hop to every app');
    $this->withoutVite()->get('https://hel.test/about')->assertOk();
    $this->withoutVite()->get('https://hel.test/status')->assertOk();
});

test('login and register on the public host bounce to the hub', function () {
    $this->get('https://hel.test/login')->assertRedirect('https://hub.hel.test/login');
    $this->get('https://hel.test/register')->assertRedirect('https://hub.hel.test/register');
    $this->get('https://hel.test/dashboard')->assertRedirect('https://hub.hel.test/dashboard');
});

test('the public host has no short link, bio or api routes', function () {
    $this->get('https://hel.test/l/abc')->assertNotFound();
    $this->get('https://hel.test/b/abc')->assertNotFound();
    $this->get('https://hel.test/api/v1/links')->assertNotFound();
    $this->get('https://hel.test/sso/linkhop')->assertNotFound();
});

test('the hub host serves accounts', function () {
    $this->withoutVite()->get('https://hub.hel.test/login')->assertOk();
    $this->get('https://hub.hel.test/')->assertRedirect('https://hub.hel.test/login');
    $user = User::factory()->create();
    $this->withoutVite()->actingAs($user)->withSession(['2fa_verified' => true])->get('https://hub.hel.test/dashboard')->assertOk()->assertSee('LinkHop');
});
