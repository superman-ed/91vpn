<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>@yield('title', '91VPN') — 91VPN</title>
    <link rel="stylesheet" href="/stisla/assets/modules/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="/stisla/assets/modules/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="/stisla/assets/css/style.css">
    <link rel="stylesheet" href="/stisla/assets/css/components.css">
    <meta name="turbo-prefetch" content="true">
    <script src="/js/turbo.min.js" defer></script>
</head>
<body>
<div id="app">
    <section class="section">
        <div class="container mt-5">
            <div class="row">
                <div class="col-12 col-sm-8 offset-sm-2 col-md-6 offset-md-3 col-lg-6 offset-lg-3 col-xl-4 offset-xl-4">
                    <div class="login-brand"><h2 style="color:#6777ef;font-weight:800">91VPN</h2></div>
                    <div class="card card-primary">
                        <div class="card-header"><h4>@yield('title', '账户')</h4></div>
                        <div class="card-body">
                            @if ($errors->any())<div class="alert alert-danger">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
                            @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
                            @yield('content')
                        </div>
                    </div>
                    <div class="simple-footer text-muted">91VPN © {{ date('Y') }}</div>
                </div>
            </div>
        </div>
    </section>
</div>
<script src="/stisla/assets/modules/jquery.min.js"></script>
<script src="/stisla/assets/modules/popper.js"></script>
<script src="/stisla/assets/modules/bootstrap/js/bootstrap.min.js"></script>
</body>
</html>
