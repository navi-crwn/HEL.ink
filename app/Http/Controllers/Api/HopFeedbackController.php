<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FeedbackReport;
use App\Models\HopApp;
use App\Models\HopAudit;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * The reports of the Feedback form for Imperium (admin_client, signed like the announcements): the list, one
 * report, its screenshot and the four actions read, done, reopen and delete. The shape is the one in
 * dokumen/42 F-4; fields that can be empty are null, never "". The report's text is returned as typed.
 */
class HopFeedbackController extends Controller
{
    public const ACTIONS = ['read', 'done', 'reopen', 'delete'];

    /** GET /api/hop/feedback?app=&kind=&status=&cursor=&limit= */
    public function index(Request $request): JsonResponse
    {
        $q = FeedbackReport::query();
        foreach (['app' => FeedbackReport::APPS, 'kind' => FeedbackReport::KINDS, 'status' => FeedbackReport::STATUSES] as $name => $allowed) {
            $value = $request->query($name);
            if ($value === null || $value === '') {
                continue;
            }
            if (! is_string($value) || ! in_array($value, $allowed, true)) {
                return $this->fail('bad_params', "$name must be one of ".implode(', ', $allowed), 422);
            }
            $q->where($name, $value);
        }
        $limit = 25;
        if ($request->query->has('limit')) {
            $n = filter_var($request->query('limit'), FILTER_VALIDATE_INT);
            if ($n === false || $n < 1) {
                return $this->fail('bad_params', 'limit must be a number from 1 to 100', 422);
            }
            $limit = min(100, $n);
        }
        $cursor = $request->query('cursor');
        if ($cursor !== null && $cursor !== '' && (! is_string($cursor) || preg_match('/^[0-9]{1,18}$/', $cursor) !== 1)) {
            return $this->fail('bad_params', 'cursor is not valid', 422);
        }
        $total = (clone $q)->count();
        if (is_string($cursor) && $cursor !== '') {
            $q->where('id', '<', (int) $cursor);
        }
        $rows = $q->orderByDesc('id')->limit($limit + 1)->get();
        $more = $rows->count() > $limit; // the next page exists only when something follows
        $rows = $rows->take($limit);

        return response()->json([
            'ok' => true,
            'data' => $rows->map(fn (FeedbackReport $r) => $this->row($r))->values()->all(),
            'page' => ['next' => $more ? (string) $rows->last()->id : null, 'total' => $total],
        ]);
    }

    /** GET /api/hop/feedback/{id} */
    public function show(string $id): JsonResponse
    {
        $report = $this->find($id);

        return $report ? response()->json(['ok' => true, 'data' => $this->detail($report)]) : $this->notFound();
    }

    /** GET /api/hop/feedback/{id}/screenshot */
    public function screenshot(string $id): Response
    {
        $report = $this->find($id);
        if (! $report) {
            return $this->notFound();
        }
        $disk = Storage::disk('local');
        if (! $report->screenshot_path || ! $disk->exists($report->screenshot_path)) {
            return $this->fail('not_found', 'This report has no image.', 404);
        }

        return response($disk->get($report->screenshot_path), 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** POST /api/hop/feedback/{id}/actions {action} */
    public function act(Request $request, string $id): JsonResponse
    {
        $action = $request->input('action');
        if (! is_string($action) || ! in_array($action, self::ACTIONS, true)) {
            return $this->fail('bad_action', 'action must be one of '.implode(', ', self::ACTIONS), 400);
        }
        if (! $this->find($id)) {
            return $this->notFound();
        }

        // The change and its audit row stand or fall together; the report is read again inside, so two calls at once
        // (two admins pressing Done) change it once and write one audit row.
        return DB::transaction(function () use ($request, $action, $id) {
            $report = FeedbackReport::whereKey((int) $id)->lockForUpdate()->first();
            if (! $report) {
                return $this->notFound();
            }
            $now = now();
            $changed = false;
            switch ($action) {
                case 'read':
                    if ($report->status === 'new') {
                        $report->update(['status' => 'read', 'read_at' => $now]);
                        $changed = true;
                    }
                    break;
                case 'done':
                    if ($report->status !== 'done') {
                        $report->update(['status' => 'done', 'done_at' => $now, 'read_at' => $report->read_at ?? $now]);
                        $changed = true;
                    }
                    break;
                case 'reopen':
                    if ($report->status === 'done') {
                        $report->update(['status' => 'read', 'done_at' => null]);
                        $changed = true;
                    }
                    break;
                case 'delete':
                    $this->audit($request, 'feedback.delete', $report);
                    $report->delete(); // the model removes the image file with the row

                    return response()->json(['ok' => true]);
            }
            if ($changed && $action !== 'read') {
                $this->audit($request, 'feedback.'.$action, $report);
            }

            return response()->json(['ok' => true, 'data' => $this->detail($report->fresh())]);
        });
    }

    // ---- helpers ----------------------------------------------------------

    /** Ids from the route are text: only plain numbers can name a report, anything else is "no such report". */
    private function find(string $id): ?FeedbackReport
    {
        return preg_match('/^[0-9]{1,18}$/', $id) === 1 ? FeedbackReport::find((int) $id) : null;
    }

    /** @return array<string, mixed> */
    private function row(FeedbackReport $r): array
    {
        $line = trim((string) preg_replace('/\s+/u', ' ', $r->message));
        if (mb_strlen($line) > 160) {
            $line = mb_substr($line, 0, 159).'…';
        }

        return [
            'id' => (string) $r->id, 'kind' => $r->kind, 'status' => $r->status, 'app' => $r->app,
            'page' => $this->blank($r->page), 'excerpt' => $line, 'has_screenshot' => $r->screenshot_path !== null,
            'username' => $this->blank($r->username), 'created_at' => $this->iso($r->created_at),
        ];
    }

    /** @return array<string, mixed> */
    private function detail(FeedbackReport $r): array
    {
        return [
            'id' => (string) $r->id, 'kind' => $r->kind, 'status' => $r->status, 'app' => $r->app,
            'page' => $this->blank($r->page), 'app_version' => $this->blank($r->app_version), 'message' => $r->message,
            'user' => $r->user_id ? ['id' => (int) $r->user_id, 'username' => $this->blank($r->username)] : null,
            'ip' => $this->blank($r->ip_address), 'user_agent' => $this->blank($r->user_agent),
            'viewport' => $this->blank($r->viewport), 'locale' => $this->blank($r->locale),
            'has_screenshot' => $r->screenshot_path !== null, 'created_at' => $this->iso($r->created_at),
            'read_at' => $this->iso($r->read_at), 'done_at' => $this->iso($r->done_at),
        ];
    }

    private function blank(?string $value): ?string
    {
        return $value === null || $value === '' ? null : $value;
    }

    private function iso(?\DateTimeInterface $t): ?string
    {
        return $t ? CarbonImmutable::instance($t)->utc()->format('Y-m-d\TH:i:s\Z') : null;
    }

    /** One audit row per change; it names the report and the actor, never the text. */
    private function audit(Request $request, string $action, FeedbackReport $r): void
    {
        /** @var HopApp $app */
        $app = $request->attributes->get('hop_app');
        HopAudit::create(['actor' => mb_substr(mb_scrub((string) $request->header('X-HOP-Actor', 'system'), 'UTF-8'), 0, 64), 'app_slug' => $app->slug, 'action' => $action,
            'target_type' => 'feedback', 'target_id' => (string) $r->id, 'reason' => null, 'params' => ['app' => $r->app, 'kind' => $r->kind]]);
    }

    private function notFound(): JsonResponse
    {
        return $this->fail('not_found', 'No such report.', 404);
    }

    private function fail(string $error, string $message, int $status): JsonResponse
    {
        return response()->json(['ok' => false, 'error' => $error, 'message' => $message], $status);
    }
}
