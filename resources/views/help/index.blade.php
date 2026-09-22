<!doctype html>
<html lang="zh">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>帮助中心 · 91VPN</title>
<link rel="icon" type="image/jpeg" href="/og.jpg">
<meta name="theme-color" content="#F1EEE4">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@700;800;900&family=JetBrains+Mono:wght@400;700&family=Noto+Sans+SC:wght@400;700&display=swap" rel="stylesheet">
<style>
:root{--bg:#F1EEE4;--ink:#1A160F;--dim:#5E5849;--faint:#928B78;--rule:#C4BCA8;--soft:#D8D2C3;--sig:#D8442A;
  --sign:"Archivo","Noto Sans SC",sans-serif;--mono:"JetBrains Mono",monospace;--body:"Noto Sans SC",sans-serif}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--ink);font-family:var(--body);line-height:1.7}
.wrap{max-width:820px;margin:0 auto;padding:44px 24px 72px}
a{color:var(--sig);text-decoration:none}a:hover{text-decoration:underline}
.top{font-family:var(--mono);font-size:12.5px;letter-spacing:.04em;margin-bottom:22px}
h1{font-family:var(--sign);font-weight:900;font-size:34px;letter-spacing:-.02em;margin:0 0 6px}
.sub{color:var(--dim);font-size:14px;margin:0}
.tabs{display:flex;flex-wrap:wrap;gap:8px;margin:26px 0 8px}
.tab{font-family:var(--mono);font-size:13px;color:var(--dim);border:1px solid var(--rule);padding:7px 14px;letter-spacing:.03em}
.tab.active{background:var(--ink);color:var(--bg);border-color:var(--ink)}
.cat{border-top:2px solid var(--ink);margin-top:34px;padding-top:16px}
.cat h2{font-family:var(--sign);font-weight:800;font-size:19px;margin:0 0 6px}
.art{display:block;border-bottom:1px solid var(--soft);padding:15px 2px;color:var(--ink);font-size:15.5px;display:flex;justify-content:space-between;align-items:center;gap:16px}
.art:hover{text-decoration:none;color:var(--sig)}
.art .arw{font-family:var(--mono);color:var(--faint)}
.art .pf{font-family:var(--mono);font-size:11px;color:var(--faint);letter-spacing:.06em;text-transform:uppercase}
.empty{border:1px solid var(--rule);padding:40px;text-align:center;color:var(--faint);margin-top:30px}
</style>
</head>
<body>
<div class="wrap">
  <div class="top"><a href="/">← 返回 91VPN</a></div>
  <h1>帮助中心</h1>
  <p class="sub">安装教程、常见问题与使用指南。</p>

  <div class="tabs">
    <a class="tab {{ $platform === '' ? 'active' : '' }}" href="/help">全部</a>
    @foreach($platforms as $key => $name)
      <a class="tab {{ $platform === $key ? 'active' : '' }}" href="/help?platform={{ $key }}">{{ $name }}</a>
    @endforeach
  </div>

  @forelse($byCategory as $category => $articles)
    <div class="cat">
      <h2>{{ $category ?: '其他' }}</h2>
      @foreach($articles as $a)
        <a class="art" href="/help/{{ $a->id }}">
          <span>{{ $a->title }}</span>
          <span class="arw">@if($a->platform !== 'all')<span class="pf">{{ $platforms[$a->platform] ?? $a->platform }}</span> @endif→</span>
        </a>
      @endforeach
    </div>
  @empty
    <div class="empty">帮助内容整理中，敬请期待。</div>
  @endforelse
</div>
</body>
</html>
