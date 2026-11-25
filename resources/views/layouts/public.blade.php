<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') · {{ sacco_name() }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: {{ theme()['bg'] }}; } .hero { background: linear-gradient(135deg, {{ theme()['brand'] }}, {{ theme()['brand2'] }}); color: #fff; }
        .card { border: 0; box-shadow: 0 2px 10px rgba(0,0,0,.06); } .step-num { width: 28px; height: 28px; border-radius: 50%; background: {{ theme()['brand2'] }}; color: #fff; display: inline-flex; align-items: center; justify-content: center; font-weight: 600; font-size: .85rem; }
    </style>
    @include('partials.theme')
</head>
<body>
<header class="hero py-3 mb-4">
    <div class="container d-flex align-items-center"><i class="bi bi-bank2 fs-3 me-2"></i><span class="fs-5 fw-semibold">{{ sacco_name() }}</span>
        <a href="{{ route('login') }}" class="btn btn-sm btn-outline-light ms-auto">Member sign in</a></div>
</header>
<main class="container pb-5">
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
    @yield('content')
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
