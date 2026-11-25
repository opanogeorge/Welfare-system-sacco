@extends('layouts.app')
@section('title', 'Guarantor · ' . $member->full_name)
@section('content')
@php($u = auth()->user())
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <h5 class="mb-0"><a href="{{ route('members.show', $member) }}">{{ $member->member_no }}</a> {{ $member->full_name }}</h5>
    <span class="badge text-bg-{{ ['eligible' => 'success', 'suspended' => 'warning', 'blocked' => 'danger'][$member->guarantor_status] ?? 'secondary' }}">{{ $member->guarantor_status }}</span>
    @if($member->guarantor_registered_at)<span class="badge text-bg-light">registered {{ $member->guarantor_registered_at->format('d/m/Y') }}</span>@endif
    <a href="{{ route('guarantors.statement', $member) }}" target="_blank" class="btn btn-sm btn-outline-secondary ms-auto"><i class="bi bi-printer"></i> Liability statement</a>
</div>
<div class="row g-3 mb-3">
    @foreach([['Guarantee limit', $p['limit'], $member->max_guarantee_amount !== null ? 'individual limit' : 'deposits ' . money($p['deposits'], 0) . ' × ' . (setting('guarantor_deposit_multiple', 1) ?: 1)],
              ['Currently guaranteeing', $p['guaranteed'], $p['active']->count() . ' loan(s)'], ['Current exposure', $p['exposure'], 'after borrowers\' repayments'],
              ['On loans >30 days late', $p['at_risk'], 'at risk of recovery'], ['Free capacity', $p['capacity'], 'can still guarantee'], ['Recovered from this member', $p['recovered'], 'on defaulted loans']] as [$l, $v, $s])
        <div class="col-6 col-lg-2"><div class="card stat h-100"><div class="card-body"><div class="label">{{ $l }}</div><div class="value">{{ money($v, 0) }}</div><small class="text-muted">{{ $s }}</small></div></div></div>
    @endforeach
</div>
<div class="row g-3">
<div class="col-lg-8">
    <div class="card"><div class="card-body">
        <h6>Guarantees</h6>
        <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead><tr><th>Loan</th><th>Borrower</th><th class="text-end">Guaranteed</th><th class="text-end">Exposure</th><th>Consent</th><th>Status</th></tr></thead>
            @forelse($p['rows'] as $g)
                <tr><td><a href="{{ route('loans.show', $g->loan) }}">{{ $g->loan->loan_no }}</a><br><small class="text-muted">{{ str_replace('_', ' ', $g->loan->status) }}@if($g->loan->status === 'disbursed' && $g->loan->daysInArrears()) · <span class="text-danger">{{ $g->loan->daysInArrears() }}d late</span>@endif</small></td>
                    <td>{{ $g->loan->member->full_name }}</td>
                    <td class="text-money">{{ money($g->amount) }}</td><td class="text-money">{{ money($g->exposure()) }}</td>
                    <td class="small">{{ $g->consent_channel }} {{ $g->responded_at?->format('d/m/Y') }}@if($g->decline_reason)<br><span class="text-muted">“{{ $g->decline_reason }}”</span>@endif</td>
                    <td><span class="badge text-bg-{{ $g->badge() }}">{{ $g->status_label }}</span>
                        @if($g->released_at)<br><small class="text-muted">{{ $g->released_at->format('d/m/Y') }} · {{ $g->release_reason }}</small>@endif
                        @if($g->recovered_amount > 0)<br><small class="text-danger">recovered {{ money($g->recovered_amount) }}</small>@endif</td></tr>
            @empty<tr><td colspan="6" class="text-muted">Has never guaranteed a loan.</td></tr>@endforelse
        </table>
        </div>
    </div></div>
</div>
<div class="col-lg-4">
    <div class="card mb-3"><div class="card-body">
        <h6>Eligibility check</h6>
        <form class="d-flex gap-1 mb-2"><input type="number" name="amount" value="{{ request('amount') }}" class="form-control form-control-sm" placeholder="Amount to guarantee" required><button class="btn btn-sm btn-outline-primary">Check</button></form>
        @if($check)
            <div class="alert alert-{{ $check['eligible'] ? 'success' : 'danger' }} small mb-1">{{ $check['eligible'] ? 'Eligible to guarantee KES ' . money(request('amount')) : 'Not eligible' }}</div>
            @foreach($check['issues'] as $i)<div class="small text-danger">✖ {{ $i }}</div>@endforeach
            @foreach($check['warnings'] as $w)<div class="small text-warning-emphasis">⚠ {{ $w }}</div>@endforeach
        @endif
    </div></div>
    @if($u->hasRole('manager', 'loan_officer') && ! $member->guarantor_registered_at)
        <form method="POST" action="{{ route('guarantors.register', $member) }}" class="mb-3">@csrf<button class="btn btn-outline-primary w-100"><i class="bi bi-person-check"></i> Register as guarantor</button></form>
    @endif
    @if($u->hasRole('manager'))
    <div class="card"><div class="card-body">
        <h6>Guarantor settings</h6>
        <form method="POST" action="{{ route('guarantors.status', $member) }}">@csrf
            <label class="form-label small mb-0">Status</label>
            <select name="guarantor_status" class="form-select form-select-sm mb-2">@foreach(['eligible' => 'Eligible', 'suspended' => 'Suspended (temporary)', 'blocked' => 'Blocked'] as $k => $v)<option value="{{ $k }}" @selected($member->guarantor_status === $k)>{{ $v }}</option>@endforeach</select>
            <input name="guarantor_note" value="{{ $member->guarantor_note }}" class="form-control form-control-sm mb-2" placeholder="Reason (required if suspended/blocked)">
            <label class="form-label small mb-0">Individual guarantee limit (KES)</label>
            <input type="number" step="0.01" name="max_guarantee_amount" value="{{ $member->max_guarantee_amount }}" class="form-control form-control-sm mb-1" placeholder="Blank = general rule">
            <div class="form-text mb-2">Overrides the deposits × multiple rule for this member.</div>
            <button class="btn btn-sm btn-primary w-100">Save</button></form>
    </div></div>
    @endif
</div>
</div>
@endsection
