<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientDownload;
use Illuminate\Http\Request;

/**
 * 客户端下载配置。
 *
 * `[!!]` 这页存在的理由：放个安装包上去不该需要改代码跑部署。
 * 此前四个平台硬编码在 User\DownloadController 里，url 全是 null。
 */
class ClientDownloadController extends Controller
{
    public function index()
    {
        return view('admin.downloads.index', ['items' => ClientDownload::orderBy('sort')->orderBy('id')->get()]);
    }

    public function create()
    {
        return view('admin.downloads.form', ['item' => new ClientDownload(['enabled' => true, 'icon' => 'fas fa-download'])]);
    }

    public function store(Request $request)
    {
        $item = ClientDownload::create($this->validated($request));
        audit('download.create', "新增客户端下载「{$item->label}」", $item);

        return redirect('/admin/downloads')->with('status', '已新增');
    }

    public function edit(ClientDownload $download)
    {
        return view('admin.downloads.form', ['item' => $download]);
    }

    public function update(Request $request, ClientDownload $download)
    {
        $before = $download->only(['url', 'version', 'enabled']);
        $download->update($this->validated($request));
        // `[!]` 审计里写清【链接改成了什么】—— 下载链接指错地方是会直接伤到用户的，
        // 而"谁在什么时候把它改成这个"是事后唯一能查的东西。
        audit('download.update', sprintf('更新客户端下载「%s」：链接 %s → %s',
            $download->label, $before['url'] ?: '（空）', $download->url ?: '（空）'), $download);

        return redirect('/admin/downloads')->with('status', '已更新');
    }

    public function destroy(ClientDownload $download)
    {
        $label = $download->label;
        $download->delete();
        audit('download.delete', "删除客户端下载「{$label}」");

        return back()->with('status', '已删除');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'platform' => ['required', 'string', 'max:32'],
            'label' => ['required', 'string', 'max:64'],
            'icon' => ['required', 'string', 'max:64'],
            // `[!!]` 只收 http/https 的绝对地址。允许相对路径或 javascript: 的话，
            // 这个字段就成了一个人人可点的注入位 —— 它会被渲染成 <a href>。
            'url' => ['nullable', 'url', 'starts_with:http://,https://', 'max:512'],
            'version' => ['nullable', 'string', 'max:32'],
            'note' => ['nullable', 'string', 'max:255'],
            'sort' => ['required', 'integer'],
            'enabled' => ['nullable'],
        ], [], [
            'platform' => '平台', 'label' => '显示名', 'icon' => '图标',
            'url' => '下载链接', 'sort' => '排序',
        ]) + ['enabled' => (bool) $request->input('enabled')];
    }
}
