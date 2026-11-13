<?php

namespace Tests\Feature;

use App\Http\Controllers\ChangelogController;
use Illuminate\Http\Request;
use Tests\TestCase;

class ChangelogTest extends TestCase
{
    public function test_history_is_the_hubs_plain_releases_in_chronological_order(): void
    {
        $history = json_decode(file_get_contents(resource_path('changelog.json')), true, flags: JSON_THROW_ON_ERROR);
        // the committed snapshot holds the hub's own releases; the other apps join it in a --family build at deploy time
        $this->assertContains('HEL.ink', array_keys($history['sources']));
        $this->assertSame(2, $history['schema']);
        $previous = PHP_INT_MAX;
        $urls = [];
        foreach ($history['entries'] as $entry) {
            $date = strtotime($entry['date']);
            $this->assertNotFalse($date);
            $this->assertLessThanOrEqual($previous, $date);
            $this->assertStringStartsWith('https://github.com/navi-crwn/'.$entry['repo'].'/', $entry['url']);
            $this->assertArrayNotHasKey($entry['url'], $urls);
            // what a reader that predates the plain releases needs, so it keeps working on this file
            foreach (['kind', 'date', 'title', 'url', 'id', 'app', 'repo'] as $field) {
                $this->assertArrayHasKey($field, $entry);
            }
            $this->assertContains($entry['kind'], ['commit', 'pr', 'tag']);
            $urls[$entry['url']] = true;
            $previous = $date;
        }
        $this->assertGreaterThan(5, count($urls));
    }

    public function test_pagination_visits_every_entry_and_filters_apps(): void
    {
        $controller = new ChangelogController;
        $first = $controller(Request::create('/changelog'))->getData();
        $count = 0;
        for ($page = 1; $page <= $first['pages']; $page++) {
            $data = $controller(Request::create('/changelog', 'GET', ['page' => $page]))->getData();
            $count += $data['entries']->count();
            $this->assertLessThanOrEqual(40, $data['entries']->count());
        }
        $this->assertSame($first['total'], $count);
        $filtered = $controller(Request::create('/changelog?app=LinkHop&page=999999'))->getData();
        $this->assertSame($filtered['pages'], $filtered['page']);
        $this->assertSame(['LinkHop'], $filtered['entries']->pluck('app')->unique()->values()->all());
        $negative = $controller(Request::create('/changelog?page=-1'))->getData();
        $this->assertSame(1, $negative['page']);
        $this->assertSame(ChangelogController::class, app('router')->getRoutes()->match(Request::create('/changelog'))->getActionName());
    }

    public function test_a_snapshot_built_at_deploy_time_wins_and_a_damaged_one_is_ignored(): void
    {
        $built = storage_path('app/changelog.json');
        @mkdir(dirname($built), 0775, true);
        $this->assertFileDoesNotExist($built);
        try {
            file_put_contents($built, json_encode(['schema' => 1, 'sources' => ['HEL.ink' => 'abc'], 'entries' => [
                ['kind' => 'pr', 'date' => '2026-11-11T00:00:00Z', 'title' => 'Built at deploy', 'url' => 'https://github.com/navi-crwn/HEL.ink/pull/1', 'id' => '#1', 'app' => 'HOP ID', 'repo' => 'HEL.ink'],
            ]]));
            $this->assertSame(['HEL.ink' => 'abc'], ChangelogController::history()['sources']);

            file_put_contents($built, '{"entries": [');
            $this->assertGreaterThan(1, count(ChangelogController::history()['sources']));

            file_put_contents($built, '{"entries": []}');   // valid JSON, but no sources: not usable either
            $this->assertGreaterThan(1, count(ChangelogController::history()['sources']));
        } finally {
            @unlink($built);
        }
    }

    public function test_the_page_shows_release_cards_with_no_internal_wording(): void
    {
        $html = $this->get('/changelog?lang=de')->assertOk()->getContent();
        $this->assertStringContainsString('Settings stay put, calmer changelog', $html);
        $this->assertStringContainsString(trans('Technical details', [], 'de'), $html);
        $this->assertStringContainsString(trans('Fixed', [], 'de'), $html);
        $this->assertSame(0, preg_match('~\btrain\b|kereta|merge pull request|remote-tracking|\b(?:fix|feat|docs)/[\w.-]+~i', strip_tags($html)));
        $this->assertStringNotContainsString('Merged pull request', $html);
    }

    public function test_every_release_has_a_plain_title_and_items_that_name_a_place(): void
    {
        $history = json_decode(file_get_contents(resource_path('changelog.json')), true, flags: JSON_THROW_ON_ERROR);
        foreach ($history['entries'] as $entry) {
            $this->assertNotSame('', trim($entry['title']));
            $this->assertNotEmpty($entry['items'], $entry['id'].' has no items');
            foreach ($entry['items'] as $item) {
                $this->assertContains($item['type'], ['added', 'changed', 'fixed', 'security', 'removed', 'deprecated']);
                $this->assertNotSame('', trim($item['where']));
                $this->assertNotSame('', trim($item['text']));
            }
        }
    }

    public function test_a_snapshot_in_the_old_form_still_renders(): void
    {
        $built = storage_path('app/changelog.json');
        @mkdir(dirname($built), 0775, true);
        try {
            file_put_contents($built, json_encode(['schema' => 1, 'sources' => ['HEL.ink' => str_repeat('a', 40)], 'entries' => [
                ['kind' => 'pr', 'date' => '2026-11-11T00:00:00Z', 'title' => 'Old style entry', 'url' => 'https://github.com/navi-crwn/HEL.ink/pull/1', 'id' => '#1', 'app' => 'HOP ID', 'repo' => 'HEL.ink'],
                ['kind' => 'tag', 'date' => '2026-11-12T00:00:00Z', 'title' => 'Plain release', 'url' => 'https://github.com/navi-crwn/HEL.ink/releases/tag/v9', 'id' => 'v9', 'app' => 'HOP ID', 'repo' => 'HEL.ink', 'version' => '9', 'summary' => '', 'items' => [['type' => 'fixed', 'where' => 'Settings', 'text' => 'A thing.']]],
            ]]));
            $this->get('/changelog')->assertOk()->assertSee('Old style entry')->assertSee('Plain release')->assertSee('Settings.', false);
        } finally {
            @unlink($built);
        }
    }
}
