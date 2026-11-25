@extends('layouts.app')
@section('title', 'Loan Recovery')
@section('content')
@php
    $stages = ['reminder' => 'Reminders', 'demand' => 'Demand letter', 'guarantors' => 'Guarantors', 'auctioneer' => 'Auctioneer', 'legal' => 'Legal'];
    $total = $loans->sum('arrears');
@endphp
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="card stat h-100"><div class="card-body"><div class="label">Loans in arrears</div><div class="value">{{ $loans->where('loan.status', 'disbursed')->count() }}</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card stat h-100"><div class="card-body"><div class="label">Total arrears</div><div class="value text-danger">{{ money($total, 0) }}</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card stat h-100"><div class="card-body"><div class="label">Written off (count / KES)</div><div class="value">{{ $writtenOff['count'] }} · {{ money($writtenOff['amount'], 0) }}</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card stat h-100"><div class="card-body"><div class="label">Recovered after write-off</div><div class="value text-success">{{ money($writtenOff['recovered'], 0) }}</div></div></div></div>
</div>
<div class="card"><div class="card-body">
    <div class="d-flex flex-wrap gap-1 mb-3">
        <a href="{{ route('loans.recovery') }}" class="btn btn-sm {{ request('stage') ? 'btn-outline-secondary' : 'btn-secondary' }}">All</a>
        @foreach($stages as $k => $v)<a href="{{ route('loans.recovery', ['stage' => $k]) }}" class="btn btn-sm {{ request('stage') === $k ? 'btn-secondary' : 'btn-outline-secondary' }}">{{ $v }}</a>@endforeach
    </div>
    <div class="table-responsive">
    <table class="table table-sm align-middle">
        <thead><tr><th>Loan</th><th>Member</th><th class="text-end">Days</th><th class="text-end">Arrears</th><th class="text-end">Outstanding</th><th>Class</th><th>Stage</th><th>Last action</th><th>Promise</th><th></th></tr></thead>
        @forelse($loans as $r)
            @php($l = $r['loan'])
            <tr class="{{ $l->status === 'written_off' ? 'table-danger' : '' }}">
                <td><a href="{{ route('loans.show', $l) }}">{{ $l->loan_no }}</a><br><small class="text-muted">{{ $l->product->name }}</small></td>
                <td>{{ $l->member->full_name }}<br><small class="text-muted">{{ $l->member->member_no }} · {{ $l->member->phone }}</small></td>
                <td class="text-end fw-semibold {{ $r['days'] > 90 ? 'text-danger' : '' }}">{{ $l->status === 'written_off' ? '—' : $r['days'] }}</td>
                <td class="text-money">{{ money($r['arrears']) }}</td>
                <td class="text-money">{{ money($l->outstanding) }}</td>
                <td><span class="badge text-bg-{{ $l->status === 'written_off' ? 'dark' : ($r['days'] > 90 ? 'danger' : ($r['days'] > 30 ? 'warning' : 'info')) }}">{{ $l->status === 'written_off' ? 'Written off' : $l->classification() }}</span></td>
                <td class="small">{{ $stages[$l->recovery_stage ?? 'reminder'] ?? $l->recovery_stage }}</td>
                <td class="small">@if($r['last']){{ \App\Services\LoanService::RECOVERY_ACTIONS[$r['last']->action] ?? $r['last']->action }}<br><span class="text-muted">{{ $r['last']->created_at->diffForHumans() }}</span>@else<span class="text-muted">none</span>@endif</td>
                <td class="small">@if($r['promise'])KES {{ money($r['promise']->promise_amount, 0) }}<br><span class="{{ $r['promise']->promise_date->isPast() ? 'text-danger' : 'text-muted' }}">by {{ $r['promise']->promise_date->format('d/m') }}</span>@endif</td>
                <td class="text-nowrap"><a href="{{ route('loans.show', $l) }}" class="btn btn-sm btn-outline-primary">Follow up</a>
                    <a href="{{ route('loans.demand-letter', $l) }}" target="_blank" class="btn btn-sm btn-outline-danger" title="Demand letter"><i class="bi bi-envelope-exclamation"></i></a></td>
            </tr>
        @empty<tr><td colspan="10" class="text-muted">No loans in arrears. 🎉</td></tr>@endforelse
    </table>
    </div>
    <p class="small text-muted mb-0">Stages move forward as you record actions on each loan (demand letter → guarantors → auctioneer → legal). Promises to pay are checked every morning.</p>
</div></div>
@endsection
