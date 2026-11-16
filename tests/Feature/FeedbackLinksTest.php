<?php

use App\Models\FeedbackReport;
use App\Models\HopApp;
use App\Models\User;
use App\Support\Feedback;
use App\Support\Username;
use Hop\Ui\Locales;

/*
 * The hub's own way in to the form (sidebar foot, public footer, Help, Contact, the sign-in pages), the Privacy
 * text, the reserved name and the nginx list. The link only shows while the feature is on.
 */

function fbl_on(): void
{
    HopApp::create(['slug' => 'imperium', 'name' => 'Imperium', 'url' => 'https://imperium.example.test',
        'callback_path' => '/auth/sso/callback', 'start_path' => '/login', 'status' => 'active',
        'secret' => 'local-test-imperium-secret-long-enough', 'capabilities' => ['admin_client'], 'hidden' => true]);
}

test('the sidebar foot has Feedback second, right after Help, in the hub tab', function () {
    fbl_on();
    $html = $this->withoutVite()->actingAs(User::factory()->create())->get('/dashboard')->assertOk()->getContent();
    preg_match('#<div class="mt-auto pt-3" data-side-foot>(.*?)</aside>#s', $html, $m);
    preg_match_all('#<li><a href="([^"]+)"([^>]*)>(.*?)</a></li>#', $m[1], $items);
    expect($items[3])->toBe(['Help', 'Feedback', 'Terms', 'Privacy', 'Changelog', 'GitHub', 'Announcement']);
    expect(html_entity_decode($items[1][1]))->toBe(url('/feedback?app=hub&from=dashboard&v='.Feedback::hubVersion()));
    expect($items[2][1])->not->toContain('target=');
});

test('the link carries the screen name of the page it is on and the hub version', function () {
    fbl_on();
    $html = $this->withoutVite()->actingAs(User::factory()->create())->get('/settings')->assertOk()->getContent();
    expect($html)->toContain('feedback?app=hub&amp;from=settings&amp;v=');
    expect(Feedback::hubVersion())->toMatch('/^\d+\.\d+\.\d+$/');
});

test('without the feature there is no link anywhere on the hub', function () {
    foreach (['/help', '/contact', '/privacy', '/login', '/faq'] as $path) {
        $this->withoutVite()->get($path)->assertOk()->assertDontSee('/feedback?');
    }
    $this->withoutVite()->actingAs(User::factory()->create())->get('/dashboard')->assertOk()->assertDontSee('/feedback?');
});

test('the public footer, Help, Contact and the sign-in pages link to the form', function () {
    fbl_on();
    foreach (['help' => 'from=help', 'contact' => 'from=contact', 'about' => 'from=about'] as $page => $from) {
        $html = $this->withoutVite()->get('/'.$page)->assertOk()->getContent();
        expect($html)->toContain('feedback?app=hub&amp;'.$from);
    }
    $this->withoutVite()->get('/help')->assertSee('Send feedback');
    $this->withoutVite()->get('/contact')->assertSee('Send feedback')->assertSee('support@hel.ink'); // the e-mail stays
    $this->withoutVite()->get('/login')->assertOk()->assertSee('feedback?app=hub&amp;from=login', false);
    // The footer link is in the Support column, right after Help.
    $footer = $this->withoutVite()->get('/terms')->getContent();
    expect($footer)->toMatch('#href="/help"[^>]*>Help</a></li>\s*<li><a href="[^"]*feedback\?app=hub&amp;from=terms[^"]*"[^>]*>Feedback</a></li>#');
});

test('the link text follows the language', function () {
    fbl_on();
    foreach (Locales::codes() as $lang) {
        $word = json_decode(file_get_contents(lang_path($lang.'.json')), true)['Feedback'];
        expect($word)->not->toBe('');
        $this->withoutVite()->get('/help?lang='.$lang)->assertOk()->assertSee('>'.e($word).'</a>', false);
    }
});

test('Privacy says what is kept and for how long, in nine languages', function () {
    foreach (Locales::codes() as $lang) {
        $map = json_decode(file_get_contents(lang_path($lang.'.json')), true);
        $html = $this->withoutVite()->get('/privacy?lang='.$lang)->assertOk()->getContent();
        foreach (['Feedback:', 'The IP address on a feedback report is deleted after 30 days; a finished report after 180 days; any other report after 12 months.'] as $key) {
            expect($map[$key] ?? '')->not->toBe('');
            if (! in_array($lang, ['en', 'de', 'pt-BR'], true)) { // German and Portuguese use the word Feedback as it is
                expect($map[$key])->not->toBe($key);
            }
            expect($html)->toContain(e($map[$key]));
        }
        $card = array_values(array_filter(array_keys($map), fn ($k) => str_starts_with($k, 'When you send feedback we keep')))[0];
        expect($html)->toContain(e($map[$card]));
    }
    // The numbers on the page are the numbers the clean-up uses.
    expect(FeedbackReport::IP_DAYS)->toBe(30)->and(FeedbackReport::DONE_DAYS)->toBe(180)->and(FeedbackReport::MAX_DAYS)->toBe(365);
});

test('feedback is a name nobody can take, and the nginx list keeps it on the hub', function () {
    expect(Username::problem('feedback'))->toBe('That username is reserved.');
    $conf = file_get_contents(base_path('deploy/nginx/hel.ink.conf'));
    preg_match('#map \$uri \$hop_hub_page \{(.*?)\}#s', $conf, $m);
    expect(explode('|', substr($m[1], strpos($m[1], '(') + 1, strpos($m[1], ')') - strpos($m[1], '(') - 1)))->toContain('feedback');
});

test('the setting is documented where self-hosting is explained', function () {
    expect(file_get_contents(base_path('.env.example')))->toContain('HOP_FEEDBACK_ENABLED');
    expect(file_get_contents(base_path('docs/self-hosting.md')))->toContain('HOP_FEEDBACK_ENABLED');
});
