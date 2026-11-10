<?php

use App\Models\HopApp;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Route;

function hubUser(?string $role = null, ?string $app = null): User
{
    $user = User::factory()->create(['avatar' => 'https://example.com/me.png']);
    if ($role) {
        $user->roles()->create(['role' => $role, 'app_slug' => $app]);
    }

    return $user;
}

function dashboardFor(User $user): string
{
    return test()->withoutVite()->actingAs($user)->withSession(['2fa_verified' => true])->get('/dashboard')->assertOk()->getContent();
}

test('an account with any role sees the Open Imperium shortcut, once, pointing at the configured address', function (?string $role, ?string $app) {
    $html = dashboardFor(hubUser($role, $app));
    expect(substr_count($html, 'data-imperium-shortcut'))->toBe(1);
    expect($html)->toContain('href="https://imperium.hel.ink"')->toContain('Open Imperium');
})->with([
    'owner' => [Role::OWNER, null],
    'admin' => [Role::ADMIN, null],
    'moderator' => [Role::MODERATOR, null],
    'support' => [Role::SUPPORT, null],
    'app admin' => [Role::ADMIN, 'linkhop'],
]);

test('an account without a role never sees the shortcut or a link to a hub admin page', function () {
    $html = dashboardFor(hubUser());
    expect($html)->not->toContain('data-imperium-shortcut')->not->toContain('Open Imperium')->not->toContain('/admin/');
});

test('the shortcut address comes from the config (HOP_IMPERIUM_URL, then HOP_CONTROL_URL)', function () {
    config()->set('hop.imperium_url', 'https://citadel.example.com');
    expect(dashboardFor(hubUser(Role::ADMIN)))->toContain('href="https://citadel.example.com"');
});

test('an admin sees the same dashboard sections as a member and no hub admin links', function () {
    $member = dashboardFor(hubUser());
    $admin = dashboardFor(hubUser(Role::ADMIN));
    foreach (['My apps', 'Security', 'Recent activity', 'Data and privacy', 'Account health'] as $section) {
        expect($member)->toContain($section);
        expect($admin)->toContain($section);
    }
    expect($admin)->not->toContain('/admin/users')->not->toContain('/admin/announcements')->not->toContain('Manage announcements');
});

test('the hub admin routes stay, for Imperium and for anyone who types the address', function () {
    foreach (['admin.users.index', 'admin.users.show', 'admin.users.suspend', 'admin.users.activate', 'admin.users.role', 'admin.users.destroy',
        'admin.announcements.index', 'admin.announcements.store', 'admin.announcements.update', 'admin.announcements.destroy'] as $name) {
        expect(Route::has($name))->toBeTrue($name);
    }
    $this->withoutVite()->actingAs(hubUser(Role::ADMIN))->withSession(['2fa_verified' => true])->get('/admin/users')->assertOk();
    $this->actingAs(hubUser())->withSession(['2fa_verified' => true])->get('/admin/users')->assertForbidden();
});

test('"Open" on the FileHop card goes to the app dashboard through SSO, not to the public page', function () {
    HopApp::factory()->filehop()->create();
    $this->actingAs(hubUser())->withSession(['2fa_verified' => true]);
    $location = $this->get('/apps/filehop')->headers->get('Location');
    expect($location)->toStartWith('https://file.example.com/auth/sso')->toContain('next=%2Fdashboard');

    config()->set('hop.apps.filehop.open_path', '/somewhere-else');
    expect($this->get('/apps/filehop')->headers->get('Location'))->toContain('next=%2Fsomewhere-else');

    foreach (['https://evil.example/x', '//evil.example', '//evil.example/x', '/\evil.example', '\evil.example', 'evil.example', '/a//b', '/a?x=1', ''] as $bad) {
        config()->set('hop.apps.filehop.open_path', $bad);   // not one absolute path: ignored, the registry page is used
        $location = $this->get('/apps/filehop')->headers->get('Location');
        expect($location)->toStartWith('https://file.example.com/auth/sso')->not->toContain('evil')->toContain('next=%2F');
        expect($location)->not->toContain('next=%2F%2F');
    }
});

test('the FileHop card carries a fixed honest line in the number area', function () {
    HopApp::factory()->filehop()->create();
    $html = dashboardFor(hubUser());
    expect($html)->toContain('Uploads')->toContain('Never')->toContain('Stored')->toContain('0 B')->not->toContain('Languages');
});

test('the dashboard cards get a token frame, also in the bare bento grids and in the Clean style', function () {
    $css = file_get_contents(resource_path('css/app.css'));
    expect($css)->toContain('--hop-frame-a: 0.8')->toContain('--hop-frame-a: 0.55')->toContain('.hop-bento[data-bare] > :is(.glass, .glass-card)');
    expect(file_get_contents(resource_path('css/coss.css')))->toContain('border: 1px solid var(--hop-frame) !important');
});
