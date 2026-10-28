<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(): View
    {
        return view('announcements.index', ['announcements' => Announcement::live()->paginate(20)]);
    }

    public function show(Announcement $announcement): View
    {
        abort_unless($announcement->isLive() || auth()->user()?->isAdmin(), 404);

        return view('announcements.show', ['announcement' => $announcement]);
    }
}
