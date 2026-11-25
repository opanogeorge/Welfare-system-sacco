@extends('layouts.app')
@section('title', 'Payroll Check-off Import')
@section('content')
<div class="row g-3">
<div class="col-lg-6">
    <div class="card"><div class="card-body">
        <form method="POST" action="{{ route('checkoff.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="mb-3"><label class="form-label">Check-off CSV file *</label><input type="file" name="file" accept=".csv,.txt" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">Source of funds</label>
                <select name="channel" class="form-select">
                    <option value="payroll">Payroll check-off (employer remits later)</option>
                    <option value="bank">Bulk bank deposit</option>
                    @if(auth()->user()->hasRole('accountant'))<option value="opening">Go-live opening balances (no cash movement)</option>@endif
                </select>
                <div class="form-text">For monthly employer check-off with expected-vs-actual reconciliation, use <a href="{{ route('employers.index') }}">Employers &amp; Check-off</a>.</div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6"><label class="form-label">Posting date *</label><input type="date" name="txn_date" value="{{ today()->toDateString() }}" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Remittance reference *</label><input name="reference" class="form-control" placeholder="e.g. NOV2026-PAYROLL" required></div>
            </div>
            <button class="btn btn-success"><i class="bi bi-upload"></i> Import &amp; post</button>
            <a href="{{ route('checkoff.template') }}" class="btn btn-link">Download template</a>
        </form>
    </div></div>
</div>
<div class="col-lg-6">
    <div class="card"><div class="card-body small">
        <h6>File format</h6>
        <p>One row per item. Columns: <code>member_no, item, amount</code></p>
        <ul>
            <li><code>item</code> = an account code (<code>SHR</code>, <code>DEP</code>, <code>SAV</code>…) to credit that account, or</li>
            <li><code>item</code> = a loan number (<code>LN2026…</code>) to post a loan repayment.</li>
        </ul>
        <p class="mb-0">Each row posts on its own. Rows that fail are listed below and nothing is posted for them, so you can fix and re-upload just those rows.</p>
    </div></div>
</div>
</div>
@if(session('import_errors'))
    <div class="card mt-3 border-danger"><div class="card-body">
        <h6 class="text-danger">{{ count(session('import_errors')) }} row(s) not posted</h6>
        <ul class="small mb-0">@foreach(session('import_errors') as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div></div>
@endif
@endsection
