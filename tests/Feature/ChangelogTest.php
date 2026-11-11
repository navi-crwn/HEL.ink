<?php

namespace Tests\Feature;

use App\Http\Controllers\ChangelogController;
use Illuminate\Http\Request;
use Tests\TestCase;

class ChangelogTest extends TestCase
{
    public function test_history_contains_every_public_app_in_chronological_order(): void
    {
        $history = json_decode(file_get_contents(resource_path('changelog.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertEqualsCanonicalizing(['HEL.ink', 'LinkHop', 'BioHop', 'PixelHop', 'NoteHop', 'FileHop', 'PayHop'], array_keys($history['sources']));
        $previous = PHP_INT_MAX;
        $urls = [];
        foreach ($history['entries'] as $entry) {
            $date = strtotime($entry['date']);
            $this->assertNotFalse($date);
            $this->assertLessThanOrEqual($previous, $date);
            $this->assertStringStartsWith('https://github.com/navi-crwn/'.$entry['repo'].'/', $entry['url']);
            $this->assertArrayNotHasKey($entry['url'], $urls);
            $urls[$entry['url']] = true;
            $previous = $date;
        }
        $this->assertGreaterThan(40, count($urls));
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
}
