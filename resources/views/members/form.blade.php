@extends('layouts.app')
@section('title', $member->exists ? "Edit {$member->member_no}" : 'Register Member')
@section('content')
<form method="POST" action="{{ $member->exists ? route('members.update', $member) : route('members.store') }}">
    @csrf @if($member->exists) @method('PUT') @endif
    <div class="card mb-3"><div class="card-body">
        <h6 class="text-success mb-3">Personal details</h6>
        <div class="row g-3">
            @foreach(['first_name' => 'First name *', 'middle_name' => 'Middle name', 'last_name' => 'Last name *'] as $f => $l)
                <div class="col-md-4"><label class="form-label">{{ $l }}</label><input name="{{ $f }}" value="{{ old($f, $member->$f) }}" class="form-control" @required(str_contains($l, '*'))></div>
            @endforeach
            <div class="col-md-3"><label class="form-label">National ID *</label><input name="national_id" value="{{ old('national_id', $member->national_id) }}" class="form-control" required></div>
            <div class="col-md-3"><label class="form-label">KRA PIN</label><input name="kra_pin" value="{{ old('kra_pin', $member->kra_pin) }}" class="form-control"></div>
            <div class="col-md-3"><label class="form-label">Gender</label>
                <select name="gender" class="form-select"><option value=""></option>
                    @foreach(['male', 'female', 'other'] as $g)<option value="{{ $g }}" @selected(old('gender', $member->gender) === $g)>{{ ucfirst($g) }}</option>@endforeach
                </select></div>
            <div class="col-md-3"><label class="form-label">Date of birth</label><input type="date" name="date_of_birth" value="{{ old('date_of_birth', $member->date_of_birth?->toDateString()) }}" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">Phone *</label><input name="phone" value="{{ old('phone', $member->phone) }}" class="form-control" placeholder="07XXXXXXXX" required></div>
            <div class="col-md-4"><label class="form-label">Email</label><input type="email" name="email" value="{{ old('email', $member->email) }}" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">Address</label><input name="address" value="{{ old('address', $member->address) }}" class="form-control"></div>
        </div>
    </div></div>

    <div class="card mb-3"><div class="card-body">
        <h6 class="text-success mb-3">Employment &amp; membership</h6>
        <div class="row g-3">
            <div class="col-md-4"><label class="form-label">Employer (check-off)</label>
                <select name="employer_id" class="form-select"><option value="">— Not on check-off —</option>
                    @foreach($employers as $e)<option value="{{ $e->id }}" @selected(old('employer_id', $member->employer_id) == $e->id)>{{ $e->name }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label">Employer name (if not listed)</label><input name="employer" value="{{ old('employer', $member->employer) }}" class="form-control"></div>
            @if(auth()->user()->isHq() && $branches->count())
            <div class="col-md-4"><label class="form-label">Branch</label>
                <select name="branch_id" class="form-select"><option value="">—</option>
                    @foreach($branches as $b)<option value="{{ $b->id }}" @selected(old('branch_id', $member->branch_id) == $b->id)>{{ $b->name }}</option>@endforeach</select></div>
            @endif
            <div class="col-md-4"><label class="form-label">Member category</label>
                <select name="member_category_id" class="form-select"><option value=""></option>
                    @foreach($categories as $c)<option value="{{ $c->id }}" @selected(old('member_category_id', $member->member_category_id) == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label">Payroll No</label><input name="payroll_no" value="{{ old('payroll_no', $member->payroll_no) }}" class="form-control"></div>
            <div class="col-md-3"><label class="form-label">Department</label><input name="department" value="{{ old('department', $member->department) }}" class="form-control"></div>
            <div class="col-md-3"><label class="form-label">Monthly contribution (KES)</label><input type="number" step="0.01" min="0" name="monthly_contribution" value="{{ old('monthly_contribution', $member->monthly_contribution ?? 0) }}" class="form-control" placeholder="0">
                <div class="form-text">Used for check-off and contribution arrears. Leave 0 if none.</div></div>
            <div class="col-md-3"><label class="form-label">Date joined *</label><input type="date" name="joined_on" value="{{ old('joined_on', $member->joined_on?->toDateString()) }}" class="form-control" required></div>
            @unless($member->exists)
                <div class="col-md-3"><label class="form-label">Initial status</label>
                    <select name="status" class="form-select"><option value="active">Active</option><option value="pending">Pending approval</option></select></div>
                <div class="col-md-6 d-flex flex-column justify-content-end">
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="create_login" value="1" id="cl" checked>
                        <label class="form-check-label" for="cl">Create member-portal login (password = ID number)</label></div>
                    @if(setting('registration_fee') > 0)
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="fee_paid" value="1" id="fp" checked>
                        <label class="form-check-label" for="fp">Registration fee of KES {{ money(setting('registration_fee')) }} paid</label></div>
                    <select name="fee_channel" class="form-select form-select-sm mt-1 w-auto"><option value="cash">Cash</option><option value="mpesa">M-Pesa</option><option value="bank">Bank</option></select>
                    @endif
                </div>
            @endunless
        </div>
    </div></div>
    <div class="card mb-3"><div class="card-body">
        <h6 class="text-success mb-3">Customer due diligence (KYC / AML) &amp; data protection</h6>
        <div class="row g-3">
            <div class="col-md-4"><label class="form-label">Occupation</label><input name="occupation" value="{{ old('occupation', $member->occupation) }}" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">Source of funds</label><input name="source_of_funds" value="{{ old('source_of_funds', $member->source_of_funds) }}" class="form-control" placeholder="Salary, business, farming…"></div>
            <div class="col-md-2"><label class="form-label">Risk rating</label><select name="risk_rating" class="form-select">@foreach(['low', 'medium', 'high'] as $r)<option @selected(old('risk_rating', $member->risk_rating ?? 'low') === $r)>{{ $r }}</option>@endforeach</select></div>
            <div class="col-md-2 d-flex align-items-end"><div class="form-check"><input type="checkbox" class="form-check-input" name="is_pep" value="1" id="pep" @checked(old('is_pep', $member->is_pep))><label class="form-check-label" for="pep">Politically exposed</label></div></div>
            <div class="col-12"><div class="form-check"><input type="checkbox" class="form-check-input" name="data_consent" value="1" id="dc" @checked($member->data_consent_at) @disabled($member->data_consent_at)>
                <label class="form-check-label" for="dc">Member has consented to the processing of their personal data (Data Protection Act, 2019) @if($member->data_consent_at)<span class="text-muted">— on {{ $member->data_consent_at->format('d M Y') }}</span>@endif</label></div></div>
        </div>
    </div></div>
    <button class="btn btn-success"><i class="bi bi-check2"></i> Save</button>
    <a href="{{ $member->exists ? route('members.show', $member) : route('members.index') }}" class="btn btn-link">Cancel</a>
</form>
@endsection
