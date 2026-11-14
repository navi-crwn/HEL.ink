<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Support\Input;
use App\Support\Reminders;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /reminders/dismiss {key}: the "Hide" button of a reminder card (data-hop-remind).
 * Stores when the signed-in person hid that reminder; the dashboard leaves it out for seven days.
 */
class ReminderController extends Controller
{
    public function dismiss(Request $request): JsonResponse
    {
        $key = Input::text($request->input('key'));
        if (! Reminders::isKey($key)) {
            return response()->json(['ok' => false, 'message' => 'Unknown reminder.'], 422);
        }
        // An announcement card: only a live one that is not critical can be hidden.
        if (str_starts_with($key, 'ann-')) {
            $announcement = Announcement::live()->find((int) substr($key, 4));
            if (! $announcement || $announcement->level === 'critical') {
                return response()->json(['ok' => false, 'message' => 'This announcement cannot be hidden.'], 422);
            }
        }
        Reminders::dismiss($request->user(), $key);

        return response()->json([
            'ok' => true,
            'key' => $key,
            'shows_again_at' => now()->addDays(Reminders::HIDE_DAYS)->toIso8601String(),
        ]);
    }
}
