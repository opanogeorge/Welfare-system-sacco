@extends('layouts.app')
@section('title', 'My Dividends')
@section('content')
<div class="card"><div class="card-body">
    <table class="table">
        <thead><tr><th>Year</th><th class="text-end">Share capital</th><th class="text-end">Dividend</th><th class="text-end">Avg deposits</th><th class="text-end">Interest</th><th class="text-end">WHT</th><th class="text-end">Net</th><th>Status</th></tr></thead>
        @forelse($payouts as $p)
            <tr><td>{{ $p->period->year }}</td><td class="text-money">{{ money($p->share_balance) }}</td><td class="text-money">{{ money($p->dividend_amount) }} <small class="text-muted">@ {{ $p->period->dividend_rate }}%</small></td>
                <td class="text-money">{{ money($p->avg_deposit_balance) }}</td><td class="text-money">{{ money($p->interest_amount) }} <small class="text-muted">@ {{ $p->period->interest_rate }}%</small></td>
                <td class="text-money">{{ money($p->tax) }}</td><td class="text-money fw-bold">{{ money($p->net) }}</td><td>{{ $p->paid ? 'Paid' : 'Declared' }}</td></tr>
        @empty <tr><td colspan="8" class="text-muted">No dividends declared yet.</td></tr> @endforelse
    </table>
</div></div>
@endsection
