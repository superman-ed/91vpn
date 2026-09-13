<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;

/**
 * 首页 Banner。
 *
 * `[!]` 图片只收 URL，不做上传 —— 上传意味着存储、权限、清理、体积限制，
 * 以及一条新的对外攻击面，而当前需求只是"换一张图"。
 */
class BannerController extends Controller
{
    public function index()
    {
        return view('admin.banners.index', ['items' => Banner::orderBy('sort')->orderBy('id')->get()]);
    }

    public function create()
    {
        return view('admin.banners.form', ['item' => new Banner(['enabled' => true, 'sort' => 0])]);
    }

    public function store(Request $request)
    {
        $item = Banner::create($this->validated($request));
        audit('banner.create', "新增 Banner「{$item->title}」", $item);

        return redirect('/admin/banners')->with('status', '已新增');
    }

    public function edit(Banner $banner)
    {
        return view('admin.banners.form', ['item' => $banner]);
    }

    public function update(Request $request, Banner $banner)
    {
        $banner->update($this->validated($request));
        audit('banner.update', "更新 Banner「{$banner->title}」", $banner);

        return redirect('/admin/banners')->with('status', '已更新');
    }

    public function destroy(Banner $banner)
    {
        $title = $banner->title;
        $banner->delete();
        audit('banner.delete', "删除 Banner「{$title}」");

        return back()->with('status', '已删除');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:128'],
            'image_url' => ['nullable', 'url', 'starts_with:http://,https://', 'max:512'],
            'link' => ['nullable', 'url', 'starts_with:http://,https://', 'max:512'],
            'text' => ['nullable', 'string', 'max:255'],
            'sort' => ['required', 'integer'],
            'enabled' => ['nullable'],
            'starts_at' => ['nullable', 'date'],
            // `[!]` 结束必须晚于开始。写反了的话这条 Banner 永远不显示,
            // 而页面上不会有任何提示 —— 人只会觉得"保存了但没生效"。
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ], [], [
            'title' => '标题', 'image_url' => '图片地址', 'link' => '跳转链接',
            'sort' => '排序', 'starts_at' => '开始时间', 'ends_at' => '结束时间',
        ]);
        $data['enabled'] = (bool) $request->input('enabled');

        return $data;
    }
}
