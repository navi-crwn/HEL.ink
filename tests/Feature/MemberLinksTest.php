<?php

use App\Models\User;
use App\Support\FamilyStory;

/**
 * A signed-in member never lands on the public home page from the logo, the Back link, the app list or the
 * Open buttons of the public pages (announcements, status, changelog, the app pages, the home page): they lead to
 * the dashboard or to the app's dashboard through the launcher. A visitor keeps the public links.
 */
function memberLinksPages(): array
{
    return ['/changelog', '/announcements', '/status', '/faq', '/about', '/products'];
}

function memberLinksHrefs(string $html): array
{
    preg_match_all('/<a [^>]*href="([^"]+)"/', $html, $m);

    return $m[1];
}

test('a member is led to the dashboard by the logo, Back and the app list of public pages', function () {
    $dashboard = hub_url('dashboard');
    $member = User::factory()->create();
    foreach (memberLinksPages() as $path) {
        $html = $this->withoutVite()->actingAs($member)->withSession(['2fa_verified' => true])->get($path)->assertOk()->getContent();
        // the logo is the first link of the top bar
        expect($html)->toMatch('#<a href="'.preg_quote($dashboard, '#').'" aria-label="HOP ID"#', "logo on $path");
        // Back to HOP
        expect($html)->toMatch('#<a href="'.preg_quote($dashboard, '#').'"[^>]*>\s*<span aria-hidden="true"[^>]*>&larr;</span>#', "back on $path");
        // the app list (drawer and top bar): every other app through the launcher, never its public address
        $links = memberLinksHrefs($html);
        expect($links)->toContain(hub_url('apps/linkhop'))->and($links)->toContain(hub_url('apps/filehop'));
        expect($links)->not->toContain(route('home'));
    }
});

test('a visitor keeps the public home page behind the logo and Back', function () {
    $home = route('home');
    foreach (memberLinksPages() as $path) {
        $html = $this->withoutVite()->get($path)->assertOk()->getContent();
        expect($html)->toMatch('#<a href="'.preg_quote($home, '#').'" aria-label="HOP ID"#', "logo on $path");
        expect(memberLinksHrefs($html))->toContain($home)->and(memberLinksHrefs($html))->not->toContain(hub_url('apps/linkhop'));
    }
});

test('Open on the home page and the app pages leads a member to the app dashboard and a visitor to the public page', function () {
    $member = User::factory()->create();
    $home = $this->withoutVite()->actingAs($member)->withSession(['2fa_verified' => true])->get(route('home'))->assertOk()->getContent();
    expect(memberLinksHrefs($home))->toContain(hub_url('apps/linkhop'));
    // the product page of an app (hel.ink/apps/{app}): its two "Open" buttons and "Back to HOP Family"
    $this->actingAs($member);
    $page = view('marketing.app', ['app' => FamilyStory::find('linkhop'), 'slug' => 'linkhop', 'status' => null])->render();
    expect(substr_count($page, 'href="'.hub_url('apps/linkhop').'"'))->toBeGreaterThanOrEqual(2)->and(memberLinksHrefs($page))->not->toContain(route('home'));

    auth()->logout();
    $guestPage = view('marketing.app', ['app' => FamilyStory::find('linkhop'), 'slug' => 'linkhop', 'status' => null])->render();
    expect(memberLinksHrefs($guestPage))->toContain(route('home'))->and(memberLinksHrefs($guestPage))->not->toContain(hub_url('apps/linkhop'));
    $guestHome = $this->withoutVite()->get(route('home'))->assertOk()->getContent();
    expect(memberLinksHrefs($guestHome))->not->toContain(hub_url('apps/linkhop'));
});
