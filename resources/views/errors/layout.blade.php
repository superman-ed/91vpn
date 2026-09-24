<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('code') · 91VPN</title>
<meta name="robots" content="noindex">
<link rel="icon" type="image/jpeg" href="{{ asset('og.jpg') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@800;900&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
  :root{color-scheme:light;--bg:#F1EEE4;--ink:#1A160F;--dim:#5E5849;--faint:#928B78;--rule:#C4BCA8;--amber:#D8442A;--amber-hi:#EA4E2E;
    --sign:"Archivo","PingFang SC","Microsoft YaHei","Noto Sans CJK SC",sans-serif;--mono:"JetBrains Mono",ui-monospace,monospace;
    --body:-apple-system,"Segoe UI","PingFang SC","Microsoft YaHei","Noto Sans CJK SC",sans-serif}
  *{margin:0;padding:0;box-sizing:border-box}
  body{min-height:100vh;display:flex;align-items:center;justify-content:center;background:var(--bg);color:var(--ink);
    font-family:var(--body);padding:24px;-webkit-font-smoothing:antialiased}
  a{color:inherit;text-decoration:none}
  /* 四角裁切标记(与官网一致) */
  .crop{position:fixed;inset:13px;pointer-events:none}
  .crop i{position:absolute;width:11px;height:11px;opacity:.55}
  .crop i::before,.crop i::after{content:"";position:absolute;background:var(--rule);top:0;left:0}
  .crop i::before{width:11px;height:1px}.crop i::after{width:1px;height:11px}
  .crop i:nth-child(1){top:0;left:0}.crop i:nth-child(2){top:0;right:0;transform:scaleX(-1)}
  .crop i:nth-child(3){bottom:0;left:0;transform:scaleY(-1)}.crop i:nth-child(4){bottom:0;right:0;transform:scale(-1)}
  .err{max-width:520px;width:100%;border:1px solid var(--rule)}
  .err-bar{display:flex;align-items:center;justify-content:space-between;padding:12px 18px;border-bottom:1px solid var(--rule);
    font-family:var(--mono);font-size:11.5px;letter-spacing:.14em;color:var(--faint);text-transform:uppercase}
  .err-bar b{font-family:var(--sign);font-weight:900;color:var(--ink);letter-spacing:.03em;text-transform:none;font-size:15px}
  .err-bar b .n{color:var(--amber)}
  .err-body{padding:40px 30px 34px}
  .err-tag{font-family:var(--mono);font-size:12px;letter-spacing:.18em;color:var(--amber);text-transform:uppercase}
  .err-code{font-family:var(--mono);font-weight:700;font-size:clamp(64px,16vw,104px);line-height:.95;letter-spacing:-.02em;margin:8px 0 10px}
  .err-title{font-family:var(--sign);font-weight:900;font-size:clamp(22px,4vw,30px);letter-spacing:-.01em}
  .err-desc{color:var(--dim);font-size:15px;line-height:1.75;margin:14px 0 28px;max-width:42ch}
  .err-cta{display:flex;flex-wrap:wrap;gap:0;border:1px solid var(--amber);width:fit-content}
  .err-cta a{font-family:var(--sign);font-weight:800;font-size:14px;padding:12px 24px;letter-spacing:.02em;transition:background-color .15s,color .15s}
  .err-cta a.solid{background:var(--amber);color:#FBF6EC}
  .err-cta a.solid:hover{background:var(--amber-hi)}
  .err-cta a.line{border-left:1px solid var(--amber);color:var(--ink)}
  .err-cta a.line:hover{color:var(--amber)}
  @media (max-width:480px){.err-body{padding:32px 22px 28px}}
</style>
</head>
<body>
<div class="crop" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
<div class="err">
  <div class="err-bar"><b>91<span class="n">VPN</span> · 国际航站楼</b><span>@yield('code')</span></div>
  <div class="err-body">
    <div class="err-tag">@yield('tag', 'NOTICE')</div>
    <div class="err-code">@yield('code')</div>
    <div class="err-title">@yield('title')</div>
    <div class="err-desc">@yield('desc')</div>
    <div class="err-cta">
      <a class="solid" href="/">返回首页</a>
      <a class="line" href="/help">帮助中心</a>
    </div>
  </div>
</div>
</body>
</html>
