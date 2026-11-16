<?php

use App\Models\FeedbackReport;
use App\Models\User;
use App\Services\UserDeletion;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/*
 * What happens to reports with time and with accounts: hop:prune-logs (IP 30 days, finished 180 days, all 365 days),
 * account deletion and the account export. Every row that goes takes its image file with it.
 */

function fbrReport(array $over = [], bool $withImage = true): FeedbackReport
{
    $r = FeedbackReport::create($over + ['kind' => 'problem', 'status' => 'new', 'app' => 'hub', 'message' => 'Something is wrong here, really.',
        'ip_address' => '203.0.113.7', 'locale' => 'en']);
    if ($withImage) {
        $path = 'feedback/'.now()->format('Y/m').'/'.bin2hex(random_bytes(20)).'.jpg';
        Storage::disk('local')->put($path, "\xFF\xD8\xFFimage");
        $r->update(['screenshot_path' => $path]);
    }

    return $r->fresh();
}

beforeEach(function () {
    Storage::fake('local');
});

test('the IP address is emptied after 30 days and nothing else changes', function () {
    $young = fbrReport();
    $this->travel(29 * 24 + 23)->hours();
    $this->artisan('hop:prune-logs')->assertSuccessful();
    expect($young->fresh()->ip_address)->toBe('203.0.113.7');
    $this->travel(2)->hours(); // 30 days and 1 hour old
    $this->artisan('hop:prune-logs')->assertSuccessful();
    $kept = $young->fresh();
    expect($kept->ip_address)->toBeNull()->and($kept->message)->toBe('Something is wrong here, really.')->and($kept->status)->toBe('new')
        ->and(Storage::disk('local')->exists($kept->screenshot_path))->toBeTrue();
});

test('a finished report goes 180 days after it was finished, with its image', function () {
    $done = fbrReport(['status' => 'done', 'done_at' => now()]);
    $path = $done->screenshot_path;
    $this->travel(179)->days();
    $this->artisan('hop:prune-logs')->assertSuccessful();
    expect(FeedbackReport::count())->toBe(1)->and(Storage::disk('local')->exists($path))->toBeTrue();
    $this->travel(2)->days();
    $this->artisan('hop:prune-logs')->assertSuccessful();
    expect(FeedbackReport::count())->toBe(0)->and(Storage::disk('local')->exists($path))->toBeFalse();
});

test('180 days count from the finish, not from the sending', function () {
    // Sent 300 days ago, finished yesterday: it stays for another 180 days from yesterday... but never past 365 days after sending.
    $this->travel(-300)->days();
    $r = fbrReport();
    $this->travelBack();
    $r->update(['status' => 'done', 'done_at' => now()->subDay()]);
    $this->artisan('hop:prune-logs')->assertSuccessful();
    expect(FeedbackReport::count())->toBe(1);
    $this->travel(70)->days(); // 370 days old: the outer limit takes it
    $this->artisan('hop:prune-logs')->assertSuccessful();
    expect(FeedbackReport::count())->toBe(0);
});

test('any report goes 365 days after it was sent, finished or not, with its image', function () {
    $new = fbrReport();
    $read = fbrReport(['status' => 'read', 'read_at' => now()]);
    $paths = [$new->screenshot_path, $read->screenshot_path];
    $this->travel(364)->days();
    $this->artisan('hop:prune-logs')->assertSuccessful();
    expect(FeedbackReport::count())->toBe(2);
    $this->travel(2)->days();
    $this->artisan('hop:prune-logs')->assertSuccessful();
    expect(FeedbackReport::count())->toBe(0);
    foreach ($paths as $p) {
        expect(Storage::disk('local')->exists($p))->toBeFalse();
    }
    expect(Storage::disk('local')->allFiles('feedback'))->toBe([]);
});

test('the clean-up handles many reports at once and leaves young ones alone', function () {
    foreach (range(1, 450) as $i) {
        fbrReport(['status' => 'done', 'done_at' => now()->subDays(200)], false);
    }
    $young = fbrReport();
    $this->artisan('hop:prune-logs')->assertSuccessful();
    expect(FeedbackReport::pluck('id')->all())->toBe([$young->id]);
});

test('the log clean-up of the days option is still the same', function () {
    $this->artisan('hop:prune-logs --days=30')->assertSuccessful()->expectsOutputToContain('feedback_reports');
});

test('the command is still scheduled daily and no other schedule touches reports', function () {
    $events = collect(app(Schedule::class)->events())->filter(fn ($e) => str_contains((string) $e->command, 'hop:prune-logs'));
    expect($events)->toHaveCount(1)->and($events->first()->expression)->toBe('0 0 * * *');
});

// ---- accounts ----------------------------------------------------------------

test('deleting an account removes its reports and their images, and only its own', function () {
    $a = User::factory()->create(['username' => 'helena']);
    $b = User::factory()->create(['username' => 'bram']);
    $mine1 = fbrReport(['user_id' => $a->id, 'username' => 'helena']);
    $mine2 = fbrReport(['user_id' => $a->id, 'username' => 'helena'], false);
    $theirs = fbrReport(['user_id' => $b->id, 'username' => 'bram']);
    $guest = fbrReport();
    app(UserDeletion::class)->delete($a);
    expect(FeedbackReport::pluck('id')->sort()->values()->all())->toBe(collect([$theirs->id, $guest->id])->sort()->values()->all());
    expect(Storage::disk('local')->exists($mine1->screenshot_path))->toBeFalse()
        ->and(Storage::disk('local')->exists($theirs->screenshot_path))->toBeTrue()
        ->and(Storage::disk('local')->exists($guest->screenshot_path))->toBeTrue();
    expect(User::find($a->id))->toBeNull();
});

test('the Delete account button of the settings does the same', function () {
    $a = User::factory()->create(['username' => 'helena']);
    $r = fbrReport(['user_id' => $a->id, 'username' => 'helena']);
    $other = fbrReport();
    $this->actingAs($a)->delete('/profile', ['password' => 'password'])->assertRedirect(route('goodbye'));
    expect(User::find($a->id))->toBeNull()->and(FeedbackReport::pluck('id')->all())->toBe([$other->id])
        ->and(Storage::disk('local')->exists($r->screenshot_path))->toBeFalse();
});

test('the account export lists the reports of that account only: kind, app, text and date', function () {
    $a = User::factory()->create(['username' => 'helena']);
    $b = User::factory()->create(['username' => 'bram']);
    fbrReport(['user_id' => $a->id, 'username' => 'helena', 'kind' => 'idea', 'app' => 'pixelhop', 'message' => 'My own idea for PixelHop.', 'user_agent' => 'AgentX'], false);
    fbrReport(['user_id' => $b->id, 'username' => 'bram', 'message' => 'Bram wrote this one, not Helena.'], false);
    fbrReport(['message' => 'A guest wrote this one for nobody.'], false);
    $res = $this->actingAs($a)->withSession(['2fa_verified' => true])->post(route('settings.export.account'));
    $res->assertOk();
    $json = json_decode($res->streamedContent(), true);
    expect($json['feedback'])->toHaveCount(1)->and(array_keys($json['feedback'][0]))->toBe(['kind', 'app', 'message', 'created_at'])
        ->and($json['feedback'][0]['kind'])->toBe('idea')->and($json['feedback'][0]['app'])->toBe('pixelhop')
        ->and($json['feedback'][0]['message'])->toBe('My own idea for PixelHop.');
    expect(json_encode($json))->not->toContain('Bram wrote')->not->toContain('guest wrote')->not->toContain('AgentX')->not->toContain('203.0.113.7');
    // An account that never wrote anything exports an empty list.
    $none = User::factory()->create();
    $res = $this->actingAs($none)->withSession(['2fa_verified' => true])->post(route('settings.export.account'));
    expect(json_decode($res->streamedContent(), true)['feedback'])->toBe([]);
});

test('a report keeps its text and username when the account row is gone but the delete went another way', function () {
    // The foreign key only empties user_id; the daily clean-up and the 365 days still apply to it.
    $a = User::factory()->create(['username' => 'helena']);
    $r = fbrReport(['user_id' => $a->id, 'username' => 'helena']);
    $a->delete();
    expect($r->fresh()->user_id)->toBeNull();
    $this->travel(366)->days();
    $this->artisan('hop:prune-logs')->assertSuccessful();
    expect(FeedbackReport::count())->toBe(0);
});

test('the migration is safe to run twice and does not touch other tables', function () {
    $migration = require base_path('database/migrations/2026_11_16_000001_create_feedback_reports_table.php');
    fbrReport([], false);
    $tables = Schema::getTableListing();
    $migration->up();
    expect(FeedbackReport::count())->toBe(1)->and(Schema::getTableListing())->toBe($tables);
    expect(Schema::hasColumns('feedback_reports', ['id', 'kind', 'status', 'app', 'page', 'app_version', 'message', 'user_id',
        'username', 'ip_address', 'user_agent', 'viewport', 'locale', 'screenshot_path', 'read_at', 'done_at', 'created_at', 'updated_at']))->toBeTrue();
});
