<?php

use App\Models\Role;
use App\Models\User;

function deleteAnchorPage(User $user): string
{
    return test()->withoutVite()->actingAs($user)->withSession(['2fa_verified' => true])->get('/settings')->assertOk()->getContent();
}

test('the delete section of the Account tab has the anchor, and the hash aliases open the Account tab and go to it', function () {
    $html = deleteAnchorPage(User::factory()->create());
    expect($html)->toContain('id="delete-account"')
        ->and($html)->toContain("delete: 'account'")
        ->and($html)->toContain("'delete-account': 'account'")
        ->and($html)->toContain("getElementById('delete-account')");
});

test('the delete warning names every family app whose data goes with the account', function () {
    $html = deleteAnchorPage(User::factory()->create());
    foreach (['LinkHop', 'BioHop', 'PixelHop', 'NoteHop', 'FileHop', 'PayHop'] as $app) {
        expect($html)->toContain($app);
    }
    expect($html)->toContain('What you have in LinkHop, BioHop, PixelHop, NoteHop, FileHop and PayHop is deleted together with your HOP ID.');
});

test('the delete warning is translated into all nine languages', function () {
    foreach (['en', 'zh-CN', 'de', 'id', 'ja', 'pt-BR', 'es', 'fr', 'ru'] as $locale) {
        $map = json_decode(file_get_contents(lang_path($locale.'.json')), true);
        foreach (['Removes your HOP ID and everything you made in the HOP family for good: LinkHop (links, analytics), BioHop (bio pages), PixelHop (images), NoteHop (notes, folders, tags), FileHop and PayHop (your account there).', 'What you have in LinkHop, BioHop, PixelHop, NoteHop, FileHop and PayHop is deleted together with your HOP ID.', 'Please sign in again to confirm it is you.'] as $key) {
            expect($map[$key] ?? null)->toBeString()->not->toBeEmpty();
            if ($locale !== 'en') {
                expect($map[$key])->not->toBe($key);
            }
            foreach (['PixelHop', 'LinkHop'] as $name) {
                if (str_contains($key, $name)) {
                    expect($map[$key])->toContain($name);
                }
            }
        }
    }
});

test('a super admin account has no delete section, so the anchor is simply absent', function () {
    $admin = User::factory()->create();
    $admin->roles()->create(['role' => Role::OWNER, 'app_slug' => null]);
    expect(deleteAnchorPage($admin))->not->toContain('id="delete-account"');
});
