<?php

use App\Models\HelpArticle;

// ─────────────────────────────────────────────────────────────────
// `[!!]` 这组测试里最要紧的一条是最后那条【文件不存在】,原因值得写清楚:
//
//   2026-09-24 实测发现 /robots.txt 返回的是 Laravel 默认的两行
//   (`User-agent: * / Disallow:`),而不是 SeoController::robots() 的输出。
//   原因是 public/robots.txt 真实存在,nginx 的
//       location / { try_files $uri $uri/ /index.php?$query_string; }
//   命中了文件就直接发,【PHP 从没被调用】。
//   后果:`Disallow: /admin` 不生效,爬虫也找不到 sitemap。
//
// `[!!]` 而下面的路由测试【抓不到那个 bug】—— feature 测试绕过 nginx 直接
//   打路由,静态文件在不在它都是绿的。这个 bug 活在 Laravel 与 nginx 的缝里。
//   所以真正的护栏是"public/ 下不许有同名文件"那一条。
// ─────────────────────────────────────────────────────────────────

it('robots.txt 排除后台与用户区，并指出 sitemap 的位置', function () {
    $body = $this->get('/robots.txt')->assertOk()->getContent();

    expect($body)->toContain('Disallow: /admin');   // 后台不该被收录
    expect($body)->toContain('Disallow: /user');
    expect($body)->toContain('Sitemap: ');          // 爬虫靠这行找 sitemap
});

it('sitemap 列出公开页，并且只列已发布的帮助文章', function () {
    $pub = HelpArticle::create(['title' => '已发布', 'content' => 'x', 'published' => true]);
    $draft = HelpArticle::create(['title' => '草稿', 'content' => 'x', 'published' => false]);

    $body = $this->get('/sitemap.xml')->assertOk()->getContent();

    expect($body)->toContain("/help/{$pub->id}<");
    expect($body)->not->toContain("/help/{$draft->id}<");
});

// `[!!]` 这一条才是对 2026-09-24 那个 bug 的护栏。
//   public/ 下任何与路由同名的文件都会被 nginx 抢先发出去,
//   而上面两条路由测试对此【完全无感】。
it('public/ 下没有会盖掉 SEO 路由的静态文件', function () {
    foreach (['robots.txt', 'sitemap.xml'] as $name) {
        expect(file_exists(public_path($name)))->toBeFalse(
            "public/{$name} 存在 —— nginx 会直接发它,SeoController 永远不会被调用。"
            .'2026-09-24 就是这么坏的:Disallow: /admin 没生效、爬虫找不到 sitemap。'
        );
    }
});
