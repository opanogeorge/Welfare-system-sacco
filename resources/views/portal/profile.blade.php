@extends('layouts.app')
@section('title', 'My Profile')
@section('content')
<div class="row g-3">
<div class="col-lg-6"><div class="card"><div class="card-body">
    <h6>Update my details</h6>
    @if($pending)<div class="alert alert-warning small">You have an update awaiting verification (submitted {{ $pending->created_at->diffForHumans() }}).</div>@endif
    <form method="POST">@csrf
        @foreach(['phone' => 'Phone', 'email' => 'Email', 'address' => 'Address', 'employer' => 'Employer', 'department' => 'Department', 'payroll_no' => 'Payroll no.', 'kra_pin' => 'KRA PIN', 'occupation' => 'Occupation'] as $f => $l)
            <div class="row mb-2 align-items-center"><label class="col-4 small">{{ $l }}</label><div class="col-8"><input name="{{ $f }}" value="{{ $member->$f }}" class="form-control form-control-sm"></div></div>
        @endforeach
        <button class="btn btn-success">Submit for verification</button>
    </form>
    <p class="small text-muted mt-2 mb-0">Name and ID number changes need a visit to the office with your documents.</p>
</div></div></div>
<div class="col-lg-6">
    <div class="card mb-3"><div class="card-body small">
        <h6>Membership</h6>
        <dl class="row mb-0"><dt class="col-5">Name</dt><dd class="col-7">{{ $member->full_name }}</dd><dt class="col-5">Member no</dt><dd class="col-7">{{ $member->member_no }}</dd>
            <dt class="col-5">ID number</dt><dd class="col-7">{{ $member->national_id }}</dd><dt class="col-5">Joined</dt><dd class="col-7">{{ $member->joined_on->format('d M Y') }}</dd></dl>
    </div></div>
    <div class="card mb-3"><div class="card-body small">
        <h6>Nominees</h6>
        <table class="table table-sm mb-0">@forelse($member->nominees as $n)<tr><td>{{ $n->name }}</td><td>{{ $n->relationship }}</td><td>{{ $n->percentage }}%</td></tr>@empty<tr><td class="text-muted">No nominees on record. Please visit the office to add them.</td></tr>@endforelse</table>
    </div></div>
    <div class="card mb-3"><div class="card-body small">
        <div class="d-flex"><h6>Recent sign-ins</h6><a href="{{ route('profile.password') }}" class="ms-auto">Change password</a></div>
        @forelse($logins as $l)
            <div class="{{ $l->action !== 'login' ? 'text-danger' : '' }}"><i class="bi bi-{{ $l->action === 'login' ? 'check-circle text-success' : 'x-circle' }}"></i>
                {{ $l->created_at->format('d M Y H:i') }} · {{ $l->action === 'login' ? 'Signed in' : 'Failed attempt' }} · IP {{ $l->ip }}</div>
        @empty <span class="text-muted">No sign-ins recorded.</span> @endforelse
        <div class="text-muted mt-2">Don't recognise one? Change your password and contact the SACCO office.</div>
    </div></div>
    <div class="card"><div class="card-body small">
        <h6>My documents</h6>
        @forelse($member->documents as $d)<div><a href="{{ route('documents.show', $d) }}" target="_blank">{{ $d->title }}</a> @if($d->verified)<i class="bi bi-patch-check-fill text-success"></i>@endif</div>@empty<span class="text-muted">None on file.</span>@endforelse
    </div></div>
</div>
</div>
@endsection
