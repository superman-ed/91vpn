<!doctype html>
<html lang="zh">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $article->title }} · 91VPN 帮助中心</title>
<meta name="theme-color" content="#F1EEE4">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@700;800;900&family=JetBrains+Mono:wght@400;700&family=Noto+Sans+SC:wght@400;700&display=swap" rel="stylesheet">
<style>
:root{--bg:#F1EEE4;--ink:#1A160F;--dim:#5E5849;--faint:#928B78;--rule:#C4BCA8;--sig:#D8442A;
  --sign:"Archivo","Noto Sans SC",sans-serif;--mono:"JetBrains Mono",monospace;--body:"Noto Sans SC",sans-serif}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--ink);font-family:var(--body);line-height:1.8}
.wrap{max-width:720px;margin:0 auto;padding:44px 24px 72px}
a{color:var(--sig);text-decoration:none}a:hover{text-decoration:underline}
.top{font-family:var(--mono);font-size:12.5px;letter-spacing:.04em;margin-bottom:22px}
.tag{font-family:var(--mono);font-size:11px;letter-spacing:.14em;color:var(--faint);text-transform:uppercase}
h1{font-family:var(--sign);font-weight:900;font-size:30px;letter-spacing:-.02em;margin:8px 0 0}
hr{border:0;border-top:2px solid var(--ink);margin:20px 0 26px}
.content{font-size:15.5px;color:var(--ink)}
.foot{margin-top:40px;border-top:1px solid var(--rule);padding-top:20px;font-family:var(--mono);font-size:13px}
</style>
</head>
<body>
<div class="wrap">
  <div class="top"><a href="/help">← 返回帮助中心</a></div>
  <span class="tag">{{ $article->category ?: '帮助' }}</span>
  <h1>{{ $article->title }}</h1>
  <hr>
  <div class="content">{!! nl2br(e($article->content)) !!}</div>
  <div class="foot"><a href="/help">← 全部帮助</a> · <a href="/">返回首页</a></div>
</div>
</body>
</html>
