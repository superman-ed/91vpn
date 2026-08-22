<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', '91VPN')</title>
    <style>
        body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#f3f6f8;margin:0;color:#34395e}
        .card{max-width:420px;margin:48px auto;background:#fff;border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,.06);padding:32px}
        h1{font-size:22px;margin:0 0 24px;text-align:center;color:#6777ef}
        label{display:block;font-size:13px;margin:14px 0 6px;color:#6c757d}
        input{width:100%;box-sizing:border-box;padding:10px 12px;border:1px solid #e3eaef;border-radius:6px;font-size:14px}
        input:focus{outline:none;border-color:#6777ef}
        .row{display:flex;gap:8px}.row input{flex:1}
        button{width:100%;margin-top:22px;padding:12px;background:#6777ef;color:#fff;border:none;border-radius:6px;font-size:15px;cursor:pointer}
        button:hover{background:#5566e0}
        .btn-sm{width:auto;margin:0;padding:10px 14px;white-space:nowrap;font-size:13px}
        .err{background:#fff0f0;color:#fc544b;padding:8px 12px;border-radius:6px;font-size:13px;margin-bottom:12px}
        .muted{text-align:center;margin-top:18px;font-size:13px;color:#6c757d}
        a{color:#6777ef;text-decoration:none}
    </style>
</head>
<body>
    <div class="card">
        <h1>@yield('title', '91VPN')</h1>
        @if ($errors->any())
            <div class="err">
                @foreach ($errors->all() as $e) <div>{{ $e }}</div> @endforeach
            </div>
        @endif
        @yield('content')
    </div>
</body>
</html>
