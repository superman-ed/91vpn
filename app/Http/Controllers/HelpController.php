<?php

namespace App\Http\Controllers;

use App\Models\HelpArticle;
use Illuminate\Http\Request;

/**
 * 公开网页帮助中心(游客可看)。此前帮助只有后台管理 + App 的 /api/help,
 * 没有面向网页的入口 —— 官网下载/FAQ 要能链过来,先补这个公开页。
 * 查询口径与 Api\HelpApiController 保持一致(published + 可选 platform + 按分类)。
 */
class HelpController extends Controller
{
    private const PLATFORMS = ['windows' => 'Windows', 'android' => 'Android', 'ios' => 'iOS', 'macos' => 'macOS'];

    public function index(Request $request)
    {
        $platform = (string) $request->query('platform', '');
        if ($platform !== '' && ! isset(self::PLATFORMS[$platform])) {
            $platform = '';
        }

        $byCategory = HelpArticle::where('published', true)
            ->when($platform !== '', fn ($q) => $q->whereIn('platform', ['all', $platform]))
            ->orderBy('category')->orderByDesc('sort')->orderBy('id')
            ->get()
            ->groupBy('category');

        return view('help.index', [
            'byCategory' => $byCategory,
            'platforms' => self::PLATFORMS,
            'platform' => $platform,
        ]);
    }

    public function show(HelpArticle $article)
    {
        abort_unless($article->published, 404);

        return view('help.show', ['article' => $article]);
    }
}
