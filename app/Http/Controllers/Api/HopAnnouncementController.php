<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\HopApp;
use App\Models\HopAudit;
use App\Models\User;
use App\Support\HopApps;
use App\Support\Input;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The HOP admin API for announcements (Imperium): list, show, create, update, publish, unpublish and
 * delete, signed like the user endpoints, for an app with the `admin_client` capability, every write
 * audited in hop_audit. The model and the public display are the hub's own (Announcement); this only
 * gives Imperium the same operations the /admin/announcements page has.
 */
class HopAnnouncementController extends Controller
{
    public const ACTIONS = ['publish', 'unpublish', 'delete'];

    private const STATES = ['draft', 'scheduled', 'live', 'expired'];

    /** GET /api/hop/announcements?state=&level=&cursor=&limit= */
    public function index(Request $request): JsonResponse
    {
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        $q = Announcement::query();
        $state = Input::text($request->query('state'));
        if (in_array($state, self::STATES, true)) {
            $this->inState($q, $state);
        }
        $level = Input::text($request->query('level'));
        if (in_array($level, Announcement::LEVELS, true)) {
            $q->where('level', $level);
        }
        $total = (clone $q)->count();
        if ($cursor = (int) $request->query('cursor', 0)) {
            $q->where('id', '<', $cursor);
        }
        $rows = $q->orderByDesc('id')->limit($limit)->get();

        return response()->json([
            'ok' => true,
            'data' => $this->rows($rows->all()),
            'page' => ['next' => $rows->count() === $limit ? (string) $rows->last()->id : null, 'total' => $total],
        ]);
    }

    /** GET /api/hop/announcements/{announcement} */
    public function show(Announcement $announcement): JsonResponse
    {
        return response()->json(['ok' => true, 'data' => $this->rows([$announcement])[0]]);
    }

    /** POST /api/hop/announcements */
    public function store(Request $request): JsonResponse
    {
        [$data, $error] = $this->validated($request);
        if ($error) {
            return $error;
        }
        $actor = $this->actor($request);
        $announcement = Announcement::create($data + ['author_id' => $this->actorUser($actor)?->id]);
        $this->audit($request, 'announcement.create', $announcement, $this->params($announcement));

        return response()->json(['ok' => true, 'data' => $this->rows([$announcement])[0]], 201);
    }

    /** PUT /api/hop/announcements/{announcement}: replaces every editable field. */
    public function update(Request $request, Announcement $announcement): JsonResponse
    {
        [$data, $error] = $this->validated($request);
        if ($error) {
            return $error;
        }
        $announcement->update($data);
        $this->audit($request, 'announcement.update', $announcement, $this->params($announcement));

        return response()->json(['ok' => true, 'data' => $this->rows([$announcement->fresh()])[0]]);
    }

    /** POST /api/hop/announcements/{announcement}/actions {action, reason} */
    public function act(Request $request, Announcement $announcement): JsonResponse
    {
        $action = Input::text($request->input('action'));
        if (! in_array($action, self::ACTIONS, true)) {
            return $this->fail('unknown_action', 'action must be one of '.implode(', ', self::ACTIONS), 422);
        }
        $row = $this->rows([$announcement])[0];
        switch ($action) {
            case 'publish':
                if ($row['state'] === 'expired' || ($announcement->expires_at && $announcement->expires_at->lte(now()))) {
                    return $this->fail('expired', 'This announcement has expired; change its expiry date first.', 409);
                }
                if ($row['state'] === 'live') {
                    return response()->json(['ok' => true, 'data' => $row]); // nothing to change, nothing to audit
                }
                $announcement->update(['published_at' => now()]);
                break;
            case 'unpublish':
                if ($announcement->published_at === null) {
                    return response()->json(['ok' => true, 'data' => $row]);
                }
                $announcement->update(['published_at' => null]);
                break;
            case 'delete':
                $announcement->delete();
                $this->audit($request, 'announcement.delete', $announcement, ['title' => $announcement->title]);

                return response()->json(['ok' => true, 'data' => $row + ['deleted' => true]]);
        }
        $this->audit($request, 'announcement.'.$action, $announcement, $this->params($announcement->fresh()));

        return response()->json(['ok' => true, 'data' => $this->rows([$announcement->fresh()])[0]]);
    }

    // ---- helpers ----------------------------------------------------------

    private function inState(Builder $q, string $state): void
    {
        $now = now();
        match ($state) {
            'draft' => $q->whereNull('published_at'),
            'scheduled' => $q->where('published_at', '>', $now),
            'expired' => $q->where('published_at', '<=', $now)->whereNotNull('expires_at')->where('expires_at', '<=', $now),
            'live' => $q->where('published_at', '<=', $now)->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>', $now)),
        };
    }

    private function state(Announcement $a): string
    {
        if ($a->published_at === null) {
            return 'draft';
        }
        if ($a->published_at->isFuture()) {
            return 'scheduled';
        }
        if ($a->expires_at && ! $a->expires_at->isFuture()) {
            return 'expired';
        }

        return 'live';
    }

    /**
     * The API objects for announcements. The author is the account that still exists ("<id>:<username>"), else
     * the actor recorded when the announcement was created, else null.
     *
     * @param  list<Announcement>  $items
     * @return list<array<string, mixed>>
     */
    private function rows(array $items): array
    {
        $ids = array_map(fn (Announcement $a) => (string) $a->id, $items);
        $users = User::whereIn('id', array_filter(array_map(fn ($a) => $a->author_id, $items)))->get()->keyBy('id');
        $created = HopAudit::where('action', 'announcement.create')->where('target_type', 'announcement')->whereIn('target_id', $ids)
            ->orderBy('id')->pluck('actor', 'target_id');

        return array_map(function (Announcement $a) use ($users, $created) {
            $u = $a->author_id ? $users->get($a->author_id) : null;
            $author = $u ? $u->id.':'.($u->username ?? '') : ($created[(string) $a->id] ?? null);

            return [
                'id' => (string) $a->id,
                'title' => $a->title,
                'body' => $a->body,
                'level' => $a->level,
                'app_slug' => $a->app_slug,
                'state' => $this->state($a),
                'published_at' => $this->iso($a->published_at),
                'expires_at' => $this->iso($a->expires_at),
                'author' => $author,
                'created_at' => $this->iso($a->created_at),
                'updated_at' => $this->iso($a->updated_at),
            ];
        }, $items);
    }

    private function iso(?\DateTimeInterface $t): ?string
    {
        return $t ? CarbonImmutable::instance($t)->utc()->format('Y-m-d\TH:i:s\Z') : null;
    }

    /** @return array<string, mixed> what the audit row keeps of a create or update */
    private function params(Announcement $a): array
    {
        return ['title' => $a->title, 'body' => $a->body, 'level' => $a->level, 'app_slug' => $a->app_slug,
            'published_at' => $this->iso($a->published_at), 'expires_at' => $this->iso($a->expires_at)];
    }

    private function actor(Request $request): string
    {
        return (string) $request->header('X-HOP-Actor', 'system');
    }

    /** The account behind `<sub>:<username>`, when there is one. */
    private function actorUser(string $actor): ?User
    {
        $sub = explode(':', $actor, 2)[0];

        return ctype_digit($sub) ? User::find((int) $sub) : null;
    }

    private function audit(Request $request, string $action, Announcement $a, array $params): void
    {
        /** @var HopApp $app */
        $app = $request->attributes->get('hop_app');
        $reason = $request->input('reason');
        HopAudit::create(['actor' => $this->actor($request), 'app_slug' => $app->slug, 'action' => $action, 'target_type' => 'announcement',
            'target_id' => (string) $a->id, 'reason' => is_string($reason) ? mb_substr($reason, 0, 500) : null, 'params' => $params]);
    }

    /**
     * The validated fields, or the 422 for the first problem. Same rules as the /admin/announcements form.
     *
     * @return array{0: array<string, mixed>, 1: ?JsonResponse}
     */
    private function validated(Request $request): array
    {
        $bad = fn (string $message) => [[], $this->fail('bad_params', $message, 422)];
        $title = $request->input('title');
        $body = $request->input('body');
        if (! is_string($title) || trim($title) === '') {
            return $bad('title is required');
        }
        if (mb_strlen($title) > 160) {
            return $bad('title may not be longer than 160 characters');
        }
        if (! is_string($body) || trim($body) === '') {
            return $bad('body is required');
        }
        if (mb_strlen($body) > 5000) {
            return $bad('body may not be longer than 5000 characters');
        }
        $level = $request->input('level');
        if (! is_string($level) || ! in_array($level, Announcement::LEVELS, true)) {
            return $bad('level must be one of '.implode(', ', Announcement::LEVELS));
        }
        $slug = $request->input('app_slug');
        if ($slug !== null && $slug !== '' && (! is_string($slug) || ! in_array($slug, array_map(fn ($a) => $a->slug, HopApps::all()), true))) {
            return $bad('app_slug must be empty or the slug of a registered app');
        }
        $published = $this->time($request->input('published_at'));
        if ($published === false) {
            return $bad('published_at must be an ISO 8601 date and time');
        }
        $expires = $this->time($request->input('expires_at'));
        if ($expires === false) {
            return $bad('expires_at must be an ISO 8601 date and time');
        }
        if ($expires && (! $published || $expires->lte($published))) {
            return $bad('expires_at must be after published_at');
        }
        if ($request->boolean('publish_now')) {
            $published = CarbonImmutable::now('UTC');
        }

        return [[
            'title' => $title, 'body' => $body, 'level' => $level, 'app_slug' => $slug ?: null,
            'published_at' => $published ? $published->setTimezone(config('app.timezone')) : null,
            'expires_at' => $expires ? $expires->setTimezone(config('app.timezone')) : null,
        ], null];
    }

    /** null (empty), a time, or false (not an ISO 8601 date and time). A missing offset counts as UTC. */
    private function time(mixed $value): CarbonImmutable|false|null
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:?\d{2})?$/', $value) !== 1) {
            return false;
        }
        try {
            return CarbonImmutable::parse($value, 'UTC')->utc();
        } catch (\Throwable) {
            return false;
        }
    }

    private function fail(string $error, string $message, int $status): JsonResponse
    {
        return response()->json(['ok' => false, 'error' => $error, 'message' => $message], $status);
    }
}
