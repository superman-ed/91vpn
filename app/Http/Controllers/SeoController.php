<?php

namespace App\Http\Controllers;

use App\Models\HelpArticle;

/**
 * robots.txt 与 sitemap.xml —— 做成路由(而非静态文件),这样域名读 config('app.url'),
 * 不写死;91vpn.com 域名切过来后自动正确。
 */
class SeoController extends Controller
{
    public function robots()
    {
        $base = rtrim(config('app.url'), '/');
        $body = implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /user',
            'Disallow: /login',
            'Disallow: /register',
            '',
            "Sitemap: {$base}/sitemap.xml",
            '',
        ]);

        return response($body, 200)->header('Content-Type', 'text/plain; charset=utf-8');
    }

    public function sitemap()
    {
        $base = rtrim(config('app.url'), '/');
        $paths = ['/', '/help', '/terms', '/privacy', '/refund'];
        foreach (HelpArticle::where('published', true)->pluck('id') as $id) {
            $paths[] = "/help/{$id}";
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($paths as $p) {
            $xml .= '  <url><loc>'.htmlspecialchars($base.$p, ENT_XML1).'</loc></url>'."\n";
        }
        $xml .= '</urlset>'."\n";

        return response($xml, 200)->header('Content-Type', 'application/xml; charset=utf-8');
    }
}
