<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function index()
    {
        return view('admin.announcements.index', ['items' => Announcement::orderByDesc('sort')->latest()->get()]);
    }

    public function create()
    {
        return view('admin.announcements.form', ['item' => new Announcement(['published' => true])]);
    }

    public function store(Request $request)
    {
        Announcement::create($this->validated($request));
        return redirect('/admin/announcements')->with('status', '公告已发布');
    }

    public function edit(Announcement $announcement)
    {
        return view('admin.announcements.form', ['item' => $announcement]);
    }

    public function update(Request $request, Announcement $announcement)
    {
        $announcement->update($this->validated($request));
        return redirect('/admin/announcements')->with('status', '公告已更新');
    }

    public function destroy(Announcement $announcement)
    {
        $announcement->delete();
        return redirect('/admin/announcements')->with('status', '公告已删除');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'sort' => ['nullable', 'integer'],
        ]);
        $data['published'] = $request->boolean('published');
        $data['sort'] ??= 0;
        return $data;
    }
}
