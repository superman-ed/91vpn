<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', '91VPN')</title>
    <link rel="stylesheet" href="/css/malio.css">
</head>
<body>
    <div class="guest-wrap">
        <div class="guest-card">
            <h1>91VPN</h1>
            <p class="tip">@yield('title', '账户')</p>
            @if ($errors->any())
                <div class="err">
                    @foreach ($errors->all() as $e) <div>{{ $e }}</div> @endforeach
                </div>
            @endif
            @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif
            @yield('content')
        </div>
    </div>
</body>
</html>
