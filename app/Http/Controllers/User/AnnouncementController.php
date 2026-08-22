<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Announcement;

class AnnouncementController extends Controller
{
    /** GET /user/announcement */
    public function index()
    {
        $announcements = Announcement::where('published', true)
            ->orderByDesc('sort')->orderByDesc('created_at')->get();

        return view('user.announcement', ['announcements' => $announcements]);
    }
}
