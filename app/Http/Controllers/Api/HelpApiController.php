<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HelpArticle;
use Illuminate\Http\Request;

class HelpApiController extends Controller
{
    /**
     * GET /api/help —— 已发布的帮助文档(公开,游客可看)。按分类分组、组内 sort 倒序。
     * 传 ?platform=windows|android|ios|macos 时,只返回该平台 + 通用(all);不传则全返(向后兼容)。
     */
    public function index(Request $request)
    {
        $platform = (string) $request->query('platform', '');
        $data = HelpArticle::where('published', true)
            ->when($platform !== '', fn ($q) => $q->whereIn('platform', ['all', $platform]))
            ->orderBy('category')
            ->orderByDesc('sort')
            ->orderBy('id')
            ->get()
            ->map(fn (HelpArticle $a) => [
                'id' => $a->id,
                'category' => $a->category,
                'title' => $a->title,
                'content' => $a->content,
            ])->values();

        return response()->json(['ret' => 1, 'data' => $data]);
    }
}
