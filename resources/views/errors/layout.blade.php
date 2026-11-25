<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code') · {{ function_exists('sacco_name') ? sacco_name() : config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>body{min-height:100vh;background:#f1f5f3;display:flex;align-items:center}.card{border:0;border-radius:1rem;box-shadow:0 6px 30px rgba(0,0,0,.08)}</style>
</head>
<body>
<div class="container"><div class="row justify-content-center"><div class="col-md-7 col-lg-5">
    <div class="card text-center p-4 p-md-5">
        <i class="bi bi-@yield('icon', 'exclamation-circle') text-@yield('color', 'secondary')" style="font-size:3.5rem"></i>
        <div class="text-muted small mt-2">Error @yield('code')</div>
        <h1 class="h4 mt-1">@yield('heading')</h1>
        <p class="text-muted">@yield('message')</p>
        <div class="d-flex gap-2 justify-content-center flex-wrap">
            <a href="javascript:history.back()" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Go back</a>
            <a href="{{ url('/') }}" class="btn btn-success"><i class="bi bi-house"></i> Home</a>
        </div>
    </div>
</div></div></div>
</body>
</html>
