@extends('layouts.app')
@section('title', 'Guarantor Liability Report')
@section('content')
<div class="row g-3 mb-3">
    @foreach([['Borrower up to date', 'current', 'success'], ['1–30 days late', 'd1_30', 'warning'], ['31–90 days late', 'd31_90', 'danger'], ['Over 90 days', 'd90', 'danger'], ['Approved, not disbursed', 'not_disbursed', 'secondary']] as [$l, $k, $c])
        <div class="col-6 col-lg"><div class="card stat h-100"><div class="card-body"><div class="label">{{ $l }}</div><div class="value text-{{ $c }}">{{ money($totals[$k], 0) }}</div><small class="text-muted">guarantor exposure</small></div></div></div>
    @endforeach
</div>
<div class="card"><div class="card-body">
    <form class="row g-2 mb-2 no-print">
        <div class="col-md-4"><input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Guarantor no. or name"></div>
        <div class="col-md-3"><select name="sort" class="form-select form-select-sm">@foreach(['exposure' => 'Sort: exposure', 'at_risk' => 'Sort: at risk', 'loans' => 'Sort: no. of loans', 'recovered' => 'Sort: recovered'] as $k => $v)<option value="{{ $k }}" @selected(request('sort', 'exposure') === $k)>{{ $v }}</option>@endforeach</select></div>
        <div class="col-md-2"><div class="form-check mt-1"><input type="checkbox" name="at_risk" value="1" class="form-check-input" id="ar" @checked(request('at_risk'))><label class="form-check-label small" for="ar">At risk only</label></div></div>
        <div class="col-md-3 d-flex gap-1"><button class="btn btn-sm btn-outline-secondary">Filter</button>
            <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn btn-sm btn-outline-secondary ms-auto"><i class="bi bi-download"></i> CSV</a>
            <button type="button" onclick="print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i></button></div>
    </form>
    <p class="small text-muted">Exposure = amount guaranteed × (principal balance ÷ principal disbursed). It falls automatically as borrowers repay and ends when the loan is cleared. "At risk" = exposure on loans more than 30 days late.</p>
    <div class="table-responsive">
    <table class="table table-sm align-middle">
        <thead><tr><th>Guarantor</th><th class="text-end">Loans</th><th class="text-end">Guaranteed</th><th class="text-end">Exposure</th><th class="text-end">At risk</th><th class="text-end">Limit</th><th class="text-end">Free</th><th class="text-end">Recovered</th><th class="no-print"></th></tr></thead>
        @forelse($rows as $r)
            <tr><td><a href="{{ route('guarantors.show', $r['member']) }}">{{ $r['member']->member_no }}</a> {{ $r['member']->full_name }}
                    <div class="small text-muted">@foreach($r['items'] as $g)<a href="{{ route('loans.show', $g->loan) }}" class="{{ $g->loan->status === 'disbursed' && $g->loan->daysInArrears() > 30 ? 'text-danger' : '' }}">{{ $g->loan->loan_no }}</a> ({{ $g->loan->member->last_name }}) @endforeach</div></td>
                <td class="text-money">{{ $r['loans'] }}</td><td class="text-money">{{ money($r['guaranteed']) }}</td><td class="text-money fw-semibold">{{ money($r['exposure']) }}</td>
                <td class="text-money {{ $r['at_risk'] > 0 ? 'text-danger fw-semibold' : '' }}">{{ money($r['at_risk']) }}</td>
                <td class="text-money">{{ money($r['limit'], 0) }}</td><td class="text-money">{{ money($r['capacity'], 0) }}</td>
                <td class="text-money">{{ $r['recovered'] > 0 ? money($r['recovered']) : '' }}</td>
                <td class="no-print"><a href="{{ route('guarantors.statement', $r['member']) }}" target="_blank" title="Liability statement"><i class="bi bi-printer"></i></a></td></tr>
        @empty <tr><td colspan="9" class="text-muted">No active guarantees.</td></tr> @endforelse
        <tfoot class="fw-bold"><tr><td colspan="2">Totals</td><td class="text-money">{{ money($rows->sum('guaranteed')) }}</td><td class="text-money">{{ money($rows->sum('exposure')) }}</td><td class="text-money">{{ money($rows->sum('at_risk')) }}</td><td colspan="2"></td><td class="text-money">{{ money($rows->sum('recovered')) }}</td><td></td></tr></tfoot>
    </table>
    </div>
</div></div>
@endsection
