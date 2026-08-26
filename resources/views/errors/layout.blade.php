<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code') · 91VPN</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC", "Microsoft YaHei", sans-serif;
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, #eef1ff 0%, #f7f8fc 100%); color: #34395e; padding: 24px;
        }
        .err { text-align: center; max-width: 460px; }
        .err-code {
            font-size: 108px; font-weight: 800; line-height: 1;
            background: linear-gradient(135deg, #6777ef, #5a67e8); -webkit-background-clip: text;
            -webkit-text-fill-color: transparent; background-clip: text; letter-spacing: -2px;
        }
        .err-ic { font-size: 40px; margin-bottom: 8px; }
        .err-title { font-size: 22px; font-weight: 700; margin: 14px 0 8px; }
        .err-desc { font-size: 15px; color: #7a869a; line-height: 1.7; margin-bottom: 26px; }
        .err-btn {
            display: inline-block; padding: 11px 30px; border-radius: 11px; text-decoration: none;
            font-weight: 600; font-size: 14px; color: #fff; background: linear-gradient(135deg, #6777ef, #5a67e8);
            box-shadow: 0 8px 20px rgba(103,119,239,.28); transition: filter .15s;
        }
        .err-btn:hover { filter: brightness(1.06); }
        .err-alt { display: block; margin-top: 16px; font-size: 13px; color: #98a6ad; text-decoration: none; }
        .err-alt:hover { color: #6777ef; }
    </style>
</head>
<body>
    <div class="err">
        <div class="err-ic">@yield('emoji', '🛠️')</div>
        <div class="err-code">@yield('code')</div>
        <div class="err-title">@yield('title')</div>
        <div class="err-desc">@yield('desc')</div>
        <a href="/" class="err-btn">返回首页</a>
        <a href="/user" class="err-alt">前往用户中心</a>
    </div>
</body>
</html>
