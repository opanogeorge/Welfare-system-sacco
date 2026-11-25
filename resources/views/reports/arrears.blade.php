@extends('layouts.app')
@section('title', 'Arrears & Portfolio at Risk')
@section('content')
<div class="card mb-3"><div class="card-body">
    <h6>SASRA loan classification &amp; provisioning</h6>
    <table class="table table-sm">
        <thead><tr><th>Class</th><th>Days in arrears</th><th class="text-end">Loans</th><th class="text-end">Principal</th><th class="text-end">% of book</th><th class="text-end">Provision rate</th><th class="text-end">Provision</th></tr></thead>
        @php($days = ['Performing' => '0', 'Watch' => '1–30', 'Substandard' => '31–180', 'Doubtful' => '181–360', 'Loss' => '> 360'])
        @php($totalProv = 0)
        @foreach($buckets as $c => $b)
            @php($prov = $b['principal'] * $provisionRates[$c] / 100) @php($totalProv += $prov)
            <tr><td>{{ $c }}</td><td>{{ $days[$c] }}</td><td class="text-money">{{ $b['count'] }}</td><td class="text-money">{{ money($b['principal']) }}</td>
                <td class="text-money">{{ $book > 0 ? number_format($b['principal'] / $book * 100, 2) : 0 }}%</td><td class="text-money">{{ $provisionRates[$c] }}%</td><td class="text-money">{{ money($prov) }}</td></tr>
        @endforeach
        <tfoot class="fw-bold"><tr><td colspan="3">Loan book</td><td class="text-money">{{ money($book) }}</td><td></td><td></td><td class="text-money">{{ money($totalProv) }}</td></tr></tfoot>
    </table>
    <p class="small text-muted mb-0">Performing counts only appear with “show all loans” on. Provision rates follow the SASRA guideline; adjust in ReportController if your board uses different rates.</p>
</div></div>
<div class="card"><div class="card-body">
    <div class="d-flex mb-2 no-print">
        <a href="?all={{ request('all') ? 0 : 1 }}" class="btn btn-sm btn-outline-secondary">{{ request('all') ? 'Arrears only' : 'Show all loans' }}</a>
        <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn btn-sm btn-outline-secondary ms-auto"><i class="bi bi-download"></i> CSV</a>
    </div>
    <table class="table table-sm table-hover">
        <thead><tr><th>Loan</th><th>Member</th><th>Phone</th><th>Product</th><th class="text-end">Outstanding</th><th class="text-end">Arrears</th><th class="text-end">Days</th><th>Class</th></tr></thead>
        @forelse($loans as $r)
            <tr><td><a href="{{ route('loans.show', $r['loan']) }}">{{ $r['loan']->loan_no }}</a></td><td>{{ $r['loan']->member->full_name }}</td><td>{{ $r['loan']->member->phone }}</td>
                <td>{{ $r['loan']->product->name }}</td><td class="text-money">{{ money($r['loan']->outstanding) }}</td><td class="text-money text-danger">{{ money($r['arrears']) }}</td>
                <td class="text-money">{{ $r['days'] }}</td><td><span class="badge text-bg-{{ $r['days'] > 180 ? 'danger' : ($r['days'] > 30 ? 'warning' : ($r['days'] ? 'info' : 'success')) }}">{{ $r['class'] }}</span></td></tr>
        @empty <tr><td colspan="8" class="text-muted text-center py-3">No loans in arrears. 🎉</td></tr> @endforelse
    </table>
</div></div>
@endsection
