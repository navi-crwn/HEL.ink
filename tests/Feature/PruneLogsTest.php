<?php

use App\Models\ActivityLog;
use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;

function logAt(User $user, int $daysAgo): int
{
    $at = now()->subDays($daysAgo);

    return DB::table('activity_logs')->insertGetId([
        'user_id' => $user->id,
        'action' => 'updated',
        'model' => 'Profile',
        'model_id' => $user->id,
        'description' => 'Updated profile: email: old@example.com → new@example.com',
        'created_at' => $at,
        'updated_at' => $at,
    ]);
}

test('hop:prune-logs deletes activity records older than 90 days and keeps newer ones', function () {
    $user = User::factory()->create();
    $old = logAt($user, 91);
    $edge = logAt($user, 89);
    $fresh = logAt($user, 1);

    $this->artisan('hop:prune-logs')->assertSuccessful();

    expect(ActivityLog::pluck('id')->all())->toEqualCanonicalizing([$edge, $fresh])
        ->and(ActivityLog::whereKey($old)->exists())->toBeFalse();
});

test('hop:prune-logs also deletes sign-in history older than 90 days', function () {
    $user = User::factory()->create();
    foreach ([120, 10] as $days) {
        DB::table('login_histories')->insert([
            'user_id' => $user->id,
            'logged_in_at' => now()->subDays($days),
            'created_at' => now()->subDays($days),
            'updated_at' => now()->subDays($days),
        ]);
    }

    $this->artisan('hop:prune-logs')->assertSuccessful();

    expect(LoginHistory::count())->toBe(1);
});

test('hop:prune-logs works through more rows than one chunk', function () {
    $user = User::factory()->create();
    $at = now()->subDays(200);
    $rows = [];
    for ($i = 0; $i < 1200; $i++) {
        $rows[] = ['user_id' => $user->id, 'action' => 'updated', 'model' => 'Profile', 'description' => 'x', 'created_at' => $at, 'updated_at' => $at];
    }
    foreach (array_chunk($rows, 300) as $chunk) {
        DB::table('activity_logs')->insert($chunk);
    }
    $keep = logAt($user, 2);

    $this->artisan('hop:prune-logs')->assertSuccessful();

    expect(ActivityLog::pluck('id')->all())->toBe([$keep]);
});

test('the prune runs on the daily schedule', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($e) => str_contains($e->command ?? '', 'hop:prune-logs'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('0 0 * * *');
});
