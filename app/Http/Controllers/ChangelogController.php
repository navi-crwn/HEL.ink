<?php

namespace App\Http\Controllers;

use App\Support\Input;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChangelogController extends Controller
{
    public function __invoke(Request $request): View
    {
        $history = self::history();
        $entries = collect($history['entries']);
        $apps = $entries->pluck('app')->unique()->values();
        $selected = Input::text($request->query('app'));
        if ($selected !== '') {
            $entries = $entries->where('app', $selected)->values();
        }
        $total = $entries->count();
        $pages = max(1, (int) ceil($total / 40));
        $page = max(1, min($pages, $request->integer('page', 1)));

        return view('changelog', [
            'entries' => $entries->slice(($page - 1) * 40, 40),
            'apps' => $apps, 'selected' => $selected, 'page' => $page,
            'pages' => $pages, 'total' => $total, 'sources' => $history['sources'],
        ]);
    }

    /**
     * The history the page shows: the snapshot built at deploy time (storage/app/changelog.json, see
     * scripts/build-changelog.py --output) when it exists and is usable, else the one in the repository.
     *
     * @return array{entries: list<array<string, mixed>>, sources: array<string, string>}
     */
    public static function history(): array
    {
        $built = storage_path('app/changelog.json');
        if (is_file($built)) {
            try {
                $history = json_decode((string) file_get_contents($built), true, flags: JSON_THROW_ON_ERROR);
                if (is_array($history) && is_array($history['entries'] ?? null) && is_array($history['sources'] ?? null)) {
                    return $history;
                }
            } catch (\JsonException) {
                // a half-written or damaged file: fall back to the snapshot in the repository
            }
        }

        return json_decode(file_get_contents(resource_path('changelog.json')), true, flags: JSON_THROW_ON_ERROR);
    }
}
