@extends('layouts.app')
@section('title', "Dividends {$period->year}")
@section('content')
<div class="card mb-3"><div class="card-body d-flex flex-wrap align-items-center gap-3">
    <div>Dividend <strong>{{ $period->dividend_rate }}%</strong> · Interest <strong>{{ $period->interest_rate }}%</strong> · WHT <strong>{{ $period->withholding_tax_rate }}%</strong> · Pay to <strong>{{ $period->payout_destination }}</strong></div>
    <span class="badge text-bg-info">{{ $period->status }}</span>
    <div class="ms-auto d-flex gap-2">
        @if(in_array($period->status, ['draft', 'computed']))
            <form method="POST" action="{{ route('dividends.compute', $period) }}">@csrf<button class="btn btn-outline-primary">{{ $period->status === 'draft' ? 'Compute' : 'Recompute' }}</button></form>
        @endif
        @if($period->status === 'computed' && auth()->user()->hasRole('manager'))
            <form method="POST" action="{{ route('dividends.approve', $period) }}" data-confirm="Approve these payouts?">@csrf<button class="btn btn-primary">Approve</button></form>
        @endif
        @if($period->status === 'approved')
            <form method="POST" action="{{ route('dividends.pay', $period) }}" data-confirm="Credit all members now? This cannot be undone.">@csrf<button class="btn btn-success">Post payouts</button></form>
        @endif
        @if($period->status !== 'draft')<a href="{{ route('dividends.export', $period) }}" class="btn btn-outline-secondary"><i class="bi bi-download"></i> CSV</a>@endif
    </div>
</div></div>
<div class="row g-3 mb-3">
    @foreach(['Gross' => $period->total_gross, 'Withholding tax' => $period->total_tax, 'Net payable' => $period->total_net] as $k => $v)
        <div class="col-md-4"><div class="card stat"><div class="card-body"><div class="label">{{ $k }}</div><div class="value">KES {{ money($v) }}</div></div></div></div>
    @endforeach
</div>
<div class="card"><div class="card-body">
    <table class="table table-sm table-hover">
        <thead><tr><th>Member</th><th class="text-end">Share capital</th><th class="text-end">Avg deposits</th><th class="text-end">Dividend</th><th class="text-end">Interest</th><th class="text-end">Gross</th><th class="text-end">WHT</th><th class="text-end">Net</th><th></th></tr></thead>
        @foreach($payouts as $p)
            <tr><td>{{ $p->member->member_no }} · {{ $p->member->full_name }}</td><td class="text-money">{{ money($p->share_balance) }}</td><td class="text-money">{{ money($p->avg_deposit_balance) }}</td>
                <td class="text-money">{{ money($p->dividend_amount) }}</td><td class="text-money">{{ money($p->interest_amount) }}</td><td class="text-money">{{ money($p->gross) }}</td>
                <td class="text-money">{{ money($p->tax) }}</td><td class="text-money fw-semibold">{{ money($p->net) }}</td><td>@if($p->paid)<i class="bi bi-check-circle text-success"></i>@endif</td></tr>
        @endforeach
    </table>
    {{ $payouts->links() }}
</div></div>
@endsection
