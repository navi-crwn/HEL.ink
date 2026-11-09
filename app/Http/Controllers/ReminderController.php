<?php

namespace App\Http\Controllers;

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
        if (! in_array($key, Reminders::KEYS, true)) {
            return response()->json(['ok' => false, 'message' => 'Unknown reminder.'], 422);
        }
        Reminders::dismiss($request->user(), $key);

        return response()->json([
            'ok' => true,
            'key' => $key,
            'shows_again_at' => now()->addDays(Reminders::HIDE_DAYS)->toIso8601String(),
        ]);
    }
}
