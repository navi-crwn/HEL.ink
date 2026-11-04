<?php

namespace Hop\Auth\Http\Controllers;

use Hop\Auth\HopEvents;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * POST /hop/events: one event from HOP ID, {id, type, at, data}. Put it behind
 * `hop.event` (this package's check) or behind the app's own signature check.
 *
 *  - an id this app has seen is answered 200 {duplicate: true} and not handled again,
 *  - a type nobody handles is answered 200 {handled: false}, so the hub does not retry it,
 *  - a handler that fails is answered 500 (no detail) and the id is forgotten, so the hub's retry runs it again.
 */
class EventController
{
    public function __invoke(Request $request): JsonResponse
    {
        $event = json_decode((string) $request->getContent(), true);
        $id = is_array($event) ? ($event['id'] ?? null) : null;
        $type = is_array($event) ? ($event['type'] ?? null) : null;
        if (! is_string($id) || $id === '' || strlen($id) > 200 || ! is_string($type) || $type === '' || strlen($type) > 64
            || (isset($event['data']) && ! is_array($event['data']))) {
            return response()->json(['ok' => false, 'error' => 'bad_request'], 400);
        }
        $key = 'hop:event:'.sha1($id);
        if (! Cache::add($key, 1, (int) config('hop-auth.events.seen_ttl', 172800))) {
            return response()->json(['ok' => true, 'data' => ['handled' => false, 'duplicate' => true]]);
        }
        try {
            $handled = HopEvents::dispatch($event);
        } catch (\Throwable $e) {
            Cache::forget($key);
            report($e);

            return response()->json(['ok' => false, 'error' => 'failed'], 500);
        }

        return response()->json(['ok' => true, 'data' => ['handled' => $handled]]);
    }
}
