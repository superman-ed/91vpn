<!doctype html>
<html lang="zh">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $title }} · 91VPN</title>
<link rel="icon" type="image/jpeg" href="/og.jpg">
<style>
body{margin:0;background:#F1EEE4;color:#1A160F;line-height:1.75;
  font-family:-apple-system,BlinkMacSystemFont,"PingFang SC","Microsoft YaHei",sans-serif}
.wrap{max-width:720px;margin:0 auto;padding:64px 24px}
h1{font-size:30px;margin:0 0 6px;letter-spacing:-.01em}
.mut{color:#5E5849}
a{color:#D8442A;text-decoration:none}
a:hover{text-decoration:underline}
hr{border:0;border-top:1px solid #C4BCA8;margin:22px 0}
</style>
@include('partials.tracking')
</head>
<body>
<div class="wrap">
  <p><a href="/">← 返回 91VPN</a></p>
  <h1>{{ $title }}</h1>
  <hr>
  @if(trim($content ?? '') !== '')
    <div style="white-space:pre-line">{{ $content }}</div>
  @else
    <p class="mut">本页内容整理中。如有疑问，请通过<a href="/user/ticket">在线客服 / 工单</a>联系我们。<br><small>（管理员可在 后台 → 站点设置 → 法务条款 填写正文）</small></p>
  @endif
</div>
</body>
</html>
