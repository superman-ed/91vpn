<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HelpArticle;

class HelpApiController extends Controller
{
    /** GET /api/help —— 已发布的帮助文档(公开,游客可看)。按分类分组、组内 sort 倒序 */
    public function index()
    {
        $data = HelpArticle::where('published', true)
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
