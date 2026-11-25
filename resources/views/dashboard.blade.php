@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
<div class="row g-3 mb-3">
    @foreach([
        ['Active members', number_format($stats['members']), '+' . $stats['new_this_month'] . ' this month', 'people', 'success'],
        ['Loan book (KES)', money($stats['loan_book'], 0), $stats['running_loans'] . ' running loans', 'cash-stack', 'primary'],
        ['Portfolio at risk >30d', $stats['par30'] . '%', 'of principal outstanding', 'exclamation-triangle', $stats['par30'] > 5 ? 'danger' : 'warning'],
        ['Loans in pipeline', $stats['pending_loans'], 'pending / approved', 'hourglass-split', 'info'],
    ] as [$label, $value, $sub, $icon, $color])
    <div class="col-sm-6 col-xl-3">
        <div class="card stat h-100"><div class="card-body d-flex">
            <div class="flex-grow-1"><div class="label">{{ $label }}</div><div class="value">{{ $value }}</div><small class="text-muted">{{ $sub }}</small></div>
            <i class="bi bi-{{ $icon }} fs-2 text-{{ $color }} opacity-75"></i>
        </div></div>
    </div>
    @endforeach
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card h-100"><div class="card-body">
            <h6 class="mb-3">Last 6 months</h6>
            <canvas id="trend" height="110"></canvas>
        </div></div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100"><div class="card-body">
            <h6 class="mb-3">Member funds</h6>
            <table class="table table-sm mb-3">
                @foreach($balances as $b)
                    <tr><td>{{ $b->name }}</td><td class="text-money fw-semibold">{{ money($b->total) }}</td></tr>
                @endforeach
            </table>
            <h6>Today</h6>
            <table class="table table-sm mb-0">
                <tr><td>Deposits</td><td class="text-money">{{ money($stats['today_deposits']) }}</td></tr>
                <tr><td>Loan repayments</td><td class="text-money">{{ money($stats['today_repayments']) }}</td></tr>
                <tr><td>Disbursed this month</td><td class="text-money">{{ money($stats['month_disbursed']) }}</td></tr>
            </table>
        </div></div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100"><div class="card-body">
            <div class="d-flex mb-2"><h6 class="mb-0">Loans awaiting action</h6><a href="{{ route('loans.index') }}" class="ms-auto small">All loans</a></div>
            <table class="table table-sm table-hover mb-0">
                <thead><tr><th>Loan</th><th>Member</th><th class="text-end">Amount</th><th>Status</th></tr></thead>
                @forelse($pending as $l)
                    <tr><td><a href="{{ route('loans.show', $l) }}">{{ $l->loan_no }}</a></td><td>{{ $l->member->full_name }}</td>
                        <td class="text-money">{{ money($l->amount_approved ?? $l->amount_applied, 0) }}</td>
                        <td><span class="badge text-bg-{{ $l->badge() }}">{{ $l->status_label }}</span></td></tr>
                @empty <tr><td colspan="4" class="text-muted">Nothing pending.</td></tr> @endforelse
            </table>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100"><div class="card-body">
            <h6 class="mb-2">Recent transactions</h6>
            <table class="table table-sm mb-0">
                @foreach($recent as $t)
                    <tr><td class="small text-muted">{{ $t->txn_date->format('d M') }}</td><td>{{ $t->member->full_name }}</td>
                        <td><span class="badge text-bg-light">{{ str_replace('_', ' ', $t->type) }}</span></td>
                        <td class="text-money {{ $t->direction === 'credit' ? 'text-success' : 'text-danger' }}">{{ money($t->amount) }}</td></tr>
                @endforeach
            </table>
        </div></div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const t = @json($trend);
new Chart(document.getElementById('trend'), {
    type: 'bar',
    data: { labels: t.map(x => x.label), datasets: [
        { label: 'Deposits', data: t.map(x => x.deposits), backgroundColor: '{{ theme()['brand2'] }}' },
        { label: 'Repayments', data: t.map(x => x.repayments), backgroundColor: '{{ theme()['accent'] }}' },
        { label: 'Disbursed', data: t.map(x => x.disbursed), backgroundColor: '#ffc107' },
    ]},
    options: { plugins: { legend: { position: 'bottom' } }, scales: { y: { ticks: { callback: v => v.toLocaleString() } } } }
});
</script>
@endpush
