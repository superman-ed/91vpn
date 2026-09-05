<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HelpArticle;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HelpArticleController extends Controller
{
    public function index()
    {
        // 文档中心:按分类分组、组内按 sort 展示
        $items = HelpArticle::orderBy('category')->orderByDesc('sort')->latest()->get();
        return view('admin.help.index', ['items' => $items]);
    }

    public function create()
    {
        return view('admin.help.form', ['item' => new HelpArticle(['published' => true, 'category' => '常见问题'])]);
    }

    public function store(Request $request)
    {
        $item = HelpArticle::create($this->validated($request));
        audit('help.create', "新增帮助文档「".Str::limit($item->title, 30)."」", $item);
        return redirect('/admin/help')->with('status', '帮助文档已新增');
    }

    public function edit(HelpArticle $help)
    {
        return view('admin.help.form', ['item' => $help]);
    }

    public function update(Request $request, HelpArticle $help)
    {
        $help->update($this->validated($request));
        audit('help.update', "更新帮助文档「".Str::limit($help->title, 30)."」", $help);
        return redirect('/admin/help')->with('status', '帮助文档已更新');
    }

    public function destroy(HelpArticle $help)
    {
        audit('help.delete', "删除帮助文档「".Str::limit($help->title, 30)."」", $help);
        $help->delete();
        return redirect('/admin/help')->with('status', '帮助文档已删除');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'category' => ['required', 'string', 'max:50'],
            'platform' => ['required', 'in:all,android,windows,ios,macos'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'sort' => ['nullable', 'integer'],
        ]);
        $data['published'] = $request->boolean('published');
        $data['sort'] ??= 0;
        return $data;
    }
}
