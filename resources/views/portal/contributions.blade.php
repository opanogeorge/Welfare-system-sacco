@extends('layouts.app')
@section('title', 'Contribution History')
@section('content')
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="card stat h-100"><div class="card-body"><div class="label">Monthly commitment</div><div class="value">{{ money($member->monthly_contribution, 0) }}</div><small class="text-muted">deposits (BOSA)</small></div></div></div>
    <div class="col-6 col-lg-3"><div class="card stat h-100"><div class="card-body"><div class="label">Paid in period</div><div class="value">{{ money($summary['deposits'], 0) }}</div><small class="text-muted">of {{ money($summary['expected'], 0) }} expected</small></div></div></div>
    <div class="col-6 col-lg-3"><div class="card stat h-100"><div class="card-body"><div class="label">Contribution arrears</div>
        <div class="value {{ $summary['arrears'] > 0 ? 'text-danger' : 'text-success' }}">{{ money($summary['arrears'], 0) }}</div><small class="text-muted">{{ $summary['months_paid'] }} of {{ count($table) }} months fully paid</small></div></div></div>
    <div class="col-6 col-lg-3"><div class="card stat h-100"><div class="card-body"><div class="label">Share capital</div><div class="value">{{ money($summary['shares'], 0) }}</div>
        @if($summary['min_shares'] > 0)
            @php($pct = min(100, $summary['shares'] / $summary['min_shares'] * 100))
            <div class="progress mt-1" style="height:6px"><div class="progress-bar bg-success" style="width: {{ $pct }}%"></div></div>
            <small class="text-muted">{{ round($pct) }}% of the KES {{ money($summary['min_shares'], 0) }} minimum</small>
        @endif
    </div></div></div>
</div>

<div class="card"><div class="card-body">
    <form class="d-flex gap-2 mb-3 no-print">
        <select name="months" class="form-select w-auto" onchange="this.form.submit()">@foreach([6, 12, 24, 36] as $m)<option value="{{ $m }}" @selected($months == $m)>Last {{ $m }} months</option>@endforeach</select>
        <a href="{{ route('portal.statement.pdf') }}" class="btn btn-outline-success ms-auto"><i class="bi bi-file-pdf"></i> Download statement</a>
    </form>
    <table class="table table-sm table-hover">
        <thead><tr><th>Month</th>@foreach($types as $t)<th class="text-end">{{ $t->name }}</th>@endforeach<th class="text-end">Total</th><th>Paid via</th><th></th></tr></thead>
        @foreach($table as $r)
            @php($dep = $r['by_type']['DEP'] ?? 0)
            <tr>
                <td>{{ $r['month']->format('M Y') }}</td>
                @foreach($types as $t)<td class="text-money">{{ ($r['by_type'][$t->code] ?? 0) > 0 ? money($r['by_type'][$t->code]) : '–' }}</td>@endforeach
                <td class="text-money fw-semibold">{{ money($r['total']) }}</td>
                <td class="small text-muted">{{ $r['channels'] }}</td>
                <td>@if($r['expected'] > 0)
                        @if($dep >= $r['expected'])<i class="bi bi-check-circle-fill text-success" title="Paid in full"></i>
                        @elseif($dep > 0)<i class="bi bi-dash-circle-fill text-warning" title="Partly paid"></i>
                        @elseif(! $r['month']->isCurrentMonth())<i class="bi bi-x-circle-fill text-danger" title="Missed"></i>@endif
                    @endif</td>
            </tr>
        @endforeach
    </table>
    <p class="small text-muted mb-0">Deposits via M-Pesa: Paybill <strong>{{ config('sacco.mpesa.paybill') }}</strong>, account <strong>{{ $member->member_no }}</strong> (deposits), <strong>{{ $member->member_no }}-SHR</strong> (shares) or <strong>{{ $member->member_no }}-SAV</strong> (savings).</p>
</div></div>
@endsection
