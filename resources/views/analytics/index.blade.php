@extends('layouts.app')
@section('title', 'Business Intelligence')
@section('content')
<form class="d-flex flex-wrap gap-2 mb-3 no-print">
    @if($branchList->count())
        <select name="branch" class="form-select w-auto"><option value="">All branches</option>@foreach($branchList as $b)<option value="{{ $b->id }}" @selected($branch == $b->id)>{{ $b->name }}</option>@endforeach</select>
    @endif
    <select name="months" class="form-select w-auto">@foreach([6, 12, 24, 36] as $m)<option value="{{ $m }}" @selected($months == $m)>Last {{ $m }} months</option>@endforeach</select>
    <button class="btn btn-outline-secondary">Apply</button>
    <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn btn-outline-secondary ms-auto"><i class="bi bi-download"></i> Data (CSV)</a>
    <button type="button" onclick="print()" class="btn btn-outline-secondary"><i class="bi bi-printer"></i></button>
</form>

<div class="row g-3 mb-3">
    @foreach([
        ['Active members', number_format($kpi['members']), $kpi['growth'] !== null ? ($kpi['growth'] >= 0 ? '+' : '') . $kpi['growth'] . '% in 12 months' : '', 'people'],
        ['Member savings', 'KES ' . money($kpi['savings'], 0), 'shares + deposits + savings', 'piggy-bank'],
        ['Loan book', 'KES ' . money($kpi['book'], 0), 'loans/savings ' . ($kpi['loans_to_savings'] ?? '—') . '%', 'cash-stack'],
        ['PAR > 30 days', $kpi['par30'] . '%', 'NPL (>90d) ' . $kpi['npl'] . '%', 'exclamation-triangle'],
        ['Collection efficiency', ($kpi['collection'] ?? '—') . '%', 'collected ÷ due', 'graph-up'],
        ['Loan yield', $kpi['loan_yield'] . '%', 'interest ÷ book, 12m', 'percent'],
        ['Cost-to-income', ($kpi['cost_income'] ?? '—') . '%', 'expenses ÷ (interest + fees)', 'speedometer'],
        ['Open fraud alerts', $kpi['open_alerts'], 'needing review', 'shield-exclamation'],
    ] as [$label, $value, $sub, $icon])
        <div class="col-6 col-xl-3"><div class="card stat h-100"><div class="card-body d-flex">
            <div class="flex-grow-1"><div class="label">{{ $label }}</div><div class="value">{{ $value }}</div><small class="text-muted">{{ $sub }}</small></div>
            <i class="bi bi-{{ $icon }} fs-3 text-success opacity-50"></i></div></div></div>
    @endforeach
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-6"><div class="card h-100"><div class="card-body"><h6>Deposits vs withdrawals</h6><canvas id="c1" height="150"></canvas></div></div></div>
    <div class="col-lg-6"><div class="card h-100"><div class="card-body"><h6>Disbursed vs repaid vs due</h6><canvas id="c2" height="150"></canvas></div></div></div>
    <div class="col-lg-6"><div class="card h-100"><div class="card-body"><h6>Collection efficiency (%)</h6><canvas id="c3" height="150"></canvas></div></div></div>
    <div class="col-lg-6"><div class="card h-100"><div class="card-body"><h6>New members per month</h6><canvas id="c4" height="150"></canvas></div></div></div>
    <div class="col-lg-4"><div class="card h-100"><div class="card-body"><h6>Portfolio aging (principal)</h6><canvas id="c5"></canvas></div></div></div>
    <div class="col-lg-4"><div class="card h-100"><div class="card-body"><h6>Loan book by product</h6><canvas id="c6"></canvas></div></div></div>
    <div class="col-lg-4"><div class="card h-100"><div class="card-body"><h6>Members by age</h6><canvas id="c7"></canvas>
        <div class="small text-muted mt-2">@foreach($gender as $g => $n){{ $g }}: {{ $n }}@if(! $loop->last) · @endif @endforeach</div></div></div></div>
</div>

<div class="row g-3">
    @if($branches->count())
    <div class="col-lg-8"><div class="card h-100"><div class="card-body">
        <h6>Branch comparison</h6>
        <table class="table table-sm">
            <thead><tr><th>Branch</th><th class="text-end">Members</th><th class="text-end">Savings</th><th class="text-end">Loan book</th><th class="text-end">PAR30</th><th class="text-end">Deposits this month</th></tr></thead>
            @foreach($branches as $r)
                <tr><td>{{ $r['branch']->name }}</td><td class="text-money">{{ number_format($r['members']) }}</td><td class="text-money">{{ money($r['savings'], 0) }}</td>
                    <td class="text-money">{{ money($r['book'], 0) }}</td><td class="text-money {{ $r['par30'] > 5 ? 'text-danger' : '' }}">{{ $r['par30'] }}%</td><td class="text-money">{{ money($r['deposits_month'], 0) }}</td></tr>
            @endforeach
        </table>
    </div></div></div>
    @endif
    <div class="col-lg-4"><div class="card h-100"><div class="card-body">
        <h6>Top employers (members)</h6>
        <table class="table table-sm mb-0">@forelse($employers as $e => $n)<tr><td>{{ $e }}</td><td class="text-money">{{ $n }}</td></tr>@empty<tr><td class="text-muted">No employer data.</td></tr>@endforelse</table>
    </div></div></div>
</div>
@endsection
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const L = @json($labels), S = @json($series), money = v => Number(v).toLocaleString();
const base = { plugins: { legend: { position: 'bottom' } }, scales: { y: { ticks: { callback: money } } } };
new Chart(c1, { type: 'bar', data: { labels: L, datasets: [{ label: 'Deposits', data: S.deposits, backgroundColor: '{{ theme()['brand2'] }}' }, { label: 'Withdrawals', data: S.withdrawals, backgroundColor: '#dc3545' }] }, options: base });
new Chart(c2, { type: 'line', data: { labels: L, datasets: [{ label: 'Disbursed', data: S.disbursed, borderColor: '#ffc107', tension: .3 }, { label: 'Repaid', data: S.repaid, borderColor: '{{ theme()['brand2'] }}', tension: .3 }, { label: 'Due', data: S.due, borderColor: '#6c757d', borderDash: [5, 5], tension: .3 }] }, options: base });
new Chart(c3, { type: 'line', data: { labels: L, datasets: [{ label: 'Collected ÷ due', data: S.efficiency, borderColor: '#0d6efd', backgroundColor: 'rgba(13,110,253,.1)', fill: true, tension: .3 }] }, options: { plugins: { legend: { display: false } }, scales: { y: { suggestedMin: 0, suggestedMax: 110 } } } });
new Chart(c4, { type: 'bar', data: { labels: L, datasets: [{ label: 'New members', data: S.members, backgroundColor: '#20c997' }] }, options: { plugins: { legend: { display: false } } } });
new Chart(c5, { type: 'doughnut', data: { labels: Object.keys(@json($buckets)), datasets: [{ data: Object.values(@json($buckets)), backgroundColor: ['#198754', '#ffc107', '#fd7e14', '#dc3545', '#6f1d1b'] }] }, options: { plugins: { legend: { position: 'bottom' } } } });
new Chart(c6, { type: 'bar', data: { labels: Object.keys(@json($byProduct)), datasets: [{ data: Object.values(@json($byProduct)), backgroundColor: '{{ theme()['brand'] }}' }] }, options: { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { ticks: { callback: money } } } } });
new Chart(c7, { type: 'bar', data: { labels: Object.keys(@json($ages)), datasets: [{ data: Object.values(@json($ages)), backgroundColor: '#6610f2' }] }, options: { plugins: { legend: { display: false } } } });
</script>
@endpush
