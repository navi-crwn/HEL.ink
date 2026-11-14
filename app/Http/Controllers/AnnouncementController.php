<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Support\Input;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(): View
    {
        return view('announcements.index', ['announcements' => Announcement::live()->paginate(20)]);
    }

    /**
     * GET /announcements.json[?app=<slug>]: the read-only family feed the apps fetch from their servers (version 1).
     * Public, no cookie (the route runs outside the web group), the same for everybody: live announcements only,
     * newest first, at most 20, for every app (`apps: ["*"]`) or for the slug asked with ?app=. Titles and bodies are
     * plain text (HTML is dropped here); nothing personal, no author. While the database is down the answer is
     * still 200 with an empty list, so an app's dashboard never fails because of it.
     */
    public function feed(Request $request): JsonResponse
    {
        $app = Input::text($request->query('app'));
        $maxAge = 300;
        try {
            $query = Announcement::live()->limit(20);
            if ($app !== '') {
                $query->where(fn ($w) => $w->whereNull('app_slug')->orWhere('app_slug', $app));
            }
            $items = $query->get()->map(fn (Announcement $a) => [
                'id' => (string) $a->id,
                'level' => in_array($a->level, Announcement::LEVELS, true) ? $a->level : 'info',
                'title' => trim(strip_tags((string) $a->title)),
                'body' => trim(strip_tags((string) $a->body)),
                'url' => rtrim((string) config('hop.public_url'), '/').'/announcements/'.$a->getRouteKey(),
                'published_at' => $a->published_at->utc()->format('Y-m-d\TH:i:s\Z'),
                'expires_at' => $a->expires_at?->utc()->format('Y-m-d\TH:i:s\Z'),
                'apps' => [$a->app_slug ?: '*'],
            ])->all();
        } catch (QueryException $e) {
            report($e);
            $items = [];
            $maxAge = 60;
        }

        return response()
            ->json(['version' => 1, 'generated_at' => now()->utc()->format('Y-m-d\TH:i:s\Z'), 'items' => $items], 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ->header('Cache-Control', 'public, max-age='.$maxAge);
    }

    public function show(Announcement $announcement): View
    {
        abort_unless($announcement->isLive() || auth()->user()?->isAdmin(), 404);

        return view('announcements.show', ['announcement' => $announcement]);
    }
}
