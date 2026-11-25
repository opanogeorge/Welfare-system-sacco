@extends('layouts.public')
@section('title', 'Application received')
@section('content')
<div class="row justify-content-center"><div class="col-md-7">
<div class="card text-center"><div class="card-body p-5">
    <i class="bi bi-check-circle-fill text-success" style="font-size:3.5rem"></i>
    <h1 class="h3 mt-3">Thank you, {{ $app['name'] }}!</h1>
    <p class="lead">Your application reference is <strong>{{ $app['member_no'] }}</strong>.</p>
    @if(setting('registration_fee'))
        <div class="alert alert-success text-start">
            <strong>Next step: pay the registration fee</strong><br>
            M-Pesa → Lipa na M-Pesa → Paybill <strong>{{ config('sacco.mpesa.paybill') }}</strong><br>
            Account: <strong>{{ $app['member_no'] }}</strong> · Amount: <strong>KES {{ money(setting('registration_fee'), 0) }}</strong>
        </div>
    @endif
    <p class="text-muted">We will verify your documents and SMS you once your membership is approved. You can then sign in with your phone number or member number and the password you chose.</p>
    <a href="{{ route('login') }}" class="btn btn-outline-success">Go to sign in</a>
</div></div>
</div></div>
@endsection
