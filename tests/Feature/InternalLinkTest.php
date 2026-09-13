<?php

use Illuminate\Support\Facades\Route;

/**
 * 收口审计 · 「指向不存在的地方」这一类。
 *
 * `[!!]` 这类失效已经咬过我们一次：保存与删除规则之后一律跳到 `/rules`，
 * 而实际路由带 `admin/` 前缀 —— 全部落在 404。那句
 * 「规则已保存，但【节点会拒绝它】」的提示**从来没人看见过**，
 * 而 647 条测试全绿：它们只写 `assertRedirect()`，
 * 那只证明"发生了跳转"，不证明目的地存在（ROUND 判据 95）。
 *
 * 所以这里做的是【静态检查】而不是逐个页面点一遍：
 * 把源码里所有内部跳转与链接抽出来，逐条确认它指向一条真实路由
 * 或一个真实存在的静态文件。
 *
 * `[!]` 这个检查【覆盖不到】什么，必须说清楚，否则它会变成一个假的安全感：
 *   - 完全由变量拼出来的地址（`redirect($someUrl)`）扫不到；
 *   - 路由存在不代表权限允许、也不代表页面不报错；
 *   - 只看路径，不看查询串。
 * 它只保证一件事：**写死在代码里的内部地址，不会指向一个不存在的路由。**
 */

/** 把路由表变成一组可匹配的正则。 */
function ilRoutePatterns(): array
{
    $out = [];
    foreach (Route::getRoutes() as $r) {
        $uri = $r->uri();
        // 可选参数：整段连同前面的斜杠一起可选
        $re = preg_replace('#/\{[^}]+\?\}#', '(?:/[^/]+)?', $uri);
        // 必选参数：一段任意非斜杠内容
        $re = preg_replace('#\{[^}]+\}#', '[^/]+', $re);
        $out[] = '#^/?'.$re.'/?$#u';
    }

    return array_values(array_unique($out));
}

/** 把代码里写的地址归一成可匹配的形状：把插值段换成通配。 */
function ilNormalize(string $path): string
{
    $path = preg_replace('#\{\{.*?\}\}#u', 'X', $path);   // Blade {{ $x }}
    $path = preg_replace('#\{\$.*?\}#u', 'X', $path);     // PHP "{$x}"
    $path = preg_replace('#\$[A-Za-z_][A-Za-z0-9_>\-\[\]\'"]*#u', 'X', $path); // 裸 $var
    $path = explode('?', $path)[0];                        // 去掉查询串
    $path = explode('#', $path)[0];

    return $path;
}

function ilResolves(string $path, array $patterns): bool
{
    $p = ilNormalize($path);
    if ($p === '' || $p === '/') {
        return true;
    }
    foreach ($patterns as $re) {
        if (preg_match($re, $p)) {
            return true;
        }
    }
    // 静态文件（样式、脚本、图片）不是路由 —— 按文件存在判。
    $file = base_path('public'.$p);

    return is_file($file);
}

it('代码里每一条写死的跳转，目标都真实存在', function () {
    $patterns = ilRoutePatterns();
    $names = collect(Route::getRoutes())->map(fn ($r) => $r->getName())->filter()->all();
    $bad = [];

    foreach (\Symfony\Component\Finder\Finder::create()->files()->in(base_path('app'))->name('*.php') as $f) {
        $text = $f->getContents();
        $rel = str_replace(base_path().'/', '', $f->getRealPath());

        // redirect('/path') / redirect("/path/{$x}")
        preg_match_all('#redirect\(\s*([\'"])(/.*?)\1#u', $text, $m, PREG_SET_ORDER);
        foreach ($m as $hit) {
            if (! ilResolves($hit[2], $patterns)) {
                $bad[] = "{$rel}: redirect('{$hit[2]}')";
            }
        }
        // redirect()->route('name')
        preg_match_all('#redirect\(\)\s*->\s*route\(\s*([\'"])(.*?)\1#u', $text, $m, PREG_SET_ORDER);
        foreach ($m as $hit) {
            if (! in_array($hit[2], $names, true)) {
                $bad[] = "{$rel}: route('{$hit[2]}') —— 没有这个路由名";
            }
        }
    }

    expect($bad)->toBe([]);
});

it('视图里每一条写死的内部链接，目标都真实存在', function () {
    $patterns = ilRoutePatterns();
    $bad = [];

    foreach (\Symfony\Component\Finder\Finder::create()->files()
        ->in(base_path('resources/views'))->name('*.blade.php') as $f) {
        $text = $f->getContents();
        $rel = str_replace(base_path().'/', '', $f->getRealPath());

        preg_match_all('#(?:href|action|formaction)\s*=\s*([\'"])(/[^\'"]*?)\1#u', $text, $m, PREG_SET_ORDER);
        foreach ($m as $hit) {
            $path = $hit[2];
            // `[!]` 完全由变量拼出来的跳过 —— 静态检查答不了它们，
            // 硬猜只会产生噪音，而有噪音的检查很快就没人看了。
            if (str_starts_with(ilNormalize($path), 'X')) {
                continue;
            }
            if (! ilResolves($path, $patterns)) {
                $bad[] = "{$rel}: {$path}";
            }
        }
    }

    expect($bad)->toBe([]);
});
