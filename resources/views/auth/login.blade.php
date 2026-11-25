<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · {{ sacco_name() }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { min-height: 100vh; background: linear-gradient(135deg, {{ theme()['brand'] }}, {{ theme()['brand2'] }}); display: flex; align-items: center; }
        .card { border: 0; border-radius: 1rem; box-shadow: 0 10px 40px rgba(0,0,0,.2); }
    </style>
    @include('partials.theme')
</head>
<body>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5 col-lg-4">
            <div class="card p-4">
                <div class="text-center mb-4">
                    <i class="bi bi-bank2 text-success" style="font-size:2.5rem"></i>
                    <h1 class="h4 mt-2 mb-0">{{ sacco_name() }}</h1>
                    <p class="text-muted small">Staff &amp; Member Portal</p>
                </div>
                @if($errors->any())<div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>@endif
                <form method="POST" action="{{ route('login') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small">Email, phone or member number</label>
                        <input name="login" value="{{ old('login') }}" class="form-control" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember">
                        <label class="form-check-label small" for="remember">Keep me signed in</label>
                    </div>
                    <button class="btn btn-success w-100">Sign in</button>
                </form>
                <p class="text-muted small text-center mt-3 mb-1">Members registered at the office: first password is your National ID number.</p>
                @if(setting('online_onboarding', 1))<p class="text-center small mb-0">Not a member yet? <a href="{{ route('join') }}">Apply online</a></p>@endif
            </div>
        </div>
    </div>
</div>
</body>
</html>
