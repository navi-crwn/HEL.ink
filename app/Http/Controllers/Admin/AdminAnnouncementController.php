<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Support\HopApps;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminAnnouncementController extends Controller
{
    public function index(): View
    {
        return view('admin.announcements', [
            'announcements' => Announcement::orderByDesc('created_at')->paginate(20),
            'apps' => HopApps::all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['author_id'] = $request->user()->id;
        Announcement::create($data);

        return back()->with('status', 'Announcement saved.');
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $announcement->update($this->validated($request));

        return back()->with('status', 'Announcement updated.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $announcement->delete();

        return back()->with('status', 'Announcement deleted.');
    }

    private function validated(Request $request): array
    {
        $slugs = array_map(fn ($a) => $a->slug, HopApps::all());
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:5000'],
            'level' => ['required', 'in:'.implode(',', Announcement::LEVELS)],
            'app_slug' => ['nullable', 'in:'.implode(',', $slugs)],
            'published_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:published_at'],
        ]);
        $data['app_slug'] = $data['app_slug'] ?: null;
        if ($request->boolean('publish_now')) {
            $data['published_at'] = now();
        }

        return $data;
    }
}
