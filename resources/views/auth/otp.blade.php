<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify · {{ sacco_name() }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>body{min-height:100vh;background:linear-gradient(135deg,{{ theme()['brand'] }},{{ theme()['brand2'] }});display:flex;align-items:center}.card{border:0;border-radius:1rem}</style>
    @include('partials.theme')
</head>
<body>
<div class="container"><div class="row justify-content-center"><div class="col-md-4">
    <div class="card p-4">
        <h1 class="h5 text-center">Enter your login code</h1>
        <p class="text-muted small text-center">We sent a 6-digit code to your phone/email. It expires in 5 minutes.</p>
        @if($errors->any())<div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('login.otp') }}">@csrf
            <input name="code" inputmode="numeric" maxlength="6" class="form-control form-control-lg text-center mb-3" autofocus required>
            <button class="btn btn-success w-100">Verify</button>
        </form>
        <a href="{{ route('login') }}" class="small text-center d-block mt-3">Back to sign in</a>
    </div>
</div></div></div>
</body>
</html>
