<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;

class AnnouncementApiController extends Controller
{
    /** GET /api/announcements —— 已发布公告(与网页版一致) */
    public function index()
    {
        $data = Announcement::where('published', true)
            ->orderByDesc('sort')->orderByDesc('created_at')->get()
            ->map(fn (Announcement $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'content' => $a->content,
                'created_at' => $a->created_at?->toDateTimeString(),
            ])->values();

        return response()->json(['ret' => 1, 'data' => $data]);
    }
}
