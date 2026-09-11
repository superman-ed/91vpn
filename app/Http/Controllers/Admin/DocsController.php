<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Support\Str;

/**
 * 后台「技术文档」页：渲染 resources/docs 下的 markdown。
 *
 * [!!] 这些文件是 sogacore/docs/guide 的副本（`php artisan docs:sync` 同步）。
 * 页面会把来源提交显示出来 —— 副本必然会过期，能做的是让过期【看得见】。
 */
class DocsController extends \App\Http\Controllers\Controller
{
    public function index(?string $slug = null)
    {
        $dir = resource_path('docs');
        $files = collect(glob($dir.'/*.md'))->map(fn ($f) => basename($f, '.md'))->sort()->values();

        if ($files->isEmpty()) {
            return view('admin.docs', [
                'files' => $files, 'current' => null, 'html' => null,
                'title' => null, 'source' => null,
            ]);
        }

        // [!] 只接受目录里真实存在的文件名，不把用户输入拼进路径 ——
        // 否则 ?slug=../../.env 就能读到本不该读的东西。
        $current = $files->contains($slug) ? $slug : ($files->contains('README') ? 'README' : $files->first());

        $md = (string) file_get_contents($dir.'/'.$current.'.md');
        $title = Str::of($md)->before("\n")->ltrim('# ')->trim()->toString() ?: $current;

        return view('admin.docs', [
            'files' => $files,
            'current' => $current,
            'title' => $title,
            'html' => Str::markdown($md, ['html_input' => 'escape', 'allow_unsafe_links' => false]),
            'source' => $this->source($dir),
            'titles' => $this->titles($files, $dir),
        ]);
    }

    /** 每篇的一级标题，做侧边目录用。 */
    private function titles($files, string $dir): array
    {
        $out = [];
        foreach ($files as $f) {
            $first = (string) strtok((string) file_get_contents($dir.'/'.$f.'.md'), "\n");
            $out[$f] = trim(ltrim($first, '# ')) ?: $f;
        }

        return $out;
    }

    private function source(string $dir): ?array
    {
        $p = $dir.'/.source.json';

        return is_file($p) ? json_decode((string) file_get_contents($p), true) : null;
    }
}
