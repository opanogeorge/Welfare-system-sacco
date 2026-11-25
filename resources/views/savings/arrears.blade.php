@extends('layouts.app')
@section('title', 'Contribution Arrears')
@section('content')
<div class="card"><div class="card-body">
    <form class="d-flex gap-2 mb-3 no-print">
        <select name="months" class="form-select w-auto">@foreach([3, 6, 12] as $m)<option value="{{ $m }}" @selected($months == $m)>Last {{ $m }} months</option>@endforeach</select>
        <button class="btn btn-outline-secondary">Run</button>
        <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn btn-outline-secondary ms-auto"><i class="bi bi-download"></i> CSV</a>
    </form>
    <p class="small text-muted">Expected = member's monthly contribution × months (from the later of joining date or period start). Paid = deposits to BOSA in the period.</p>
    <table class="table table-sm table-hover">
        <thead><tr><th>Member</th><th>Phone</th><th class="text-end">Monthly</th><th class="text-end">Months</th><th class="text-end">Expected</th><th class="text-end">Paid</th><th class="text-end">Arrears</th></tr></thead>
        @foreach($rows as $r)
            <tr class="{{ $r['arrears'] > 0 ? '' : 'text-muted' }}"><td><a href="{{ route('members.show', $r['member']) }}">{{ $r['member']->member_no }}</a> {{ $r['member']->full_name }}</td><td>{{ $r['member']->phone }}</td>
                <td class="text-money">{{ money($r['member']->monthly_contribution) }}</td><td class="text-money">{{ $r['months'] }}</td>
                <td class="text-money">{{ money($r['expected']) }}</td><td class="text-money">{{ money($r['actual']) }}</td>
                <td class="text-money {{ $r['arrears'] > 0 ? 'text-danger fw-semibold' : '' }}">{{ money($r['arrears']) }}</td></tr>
        @endforeach
        <tfoot class="fw-bold"><tr><td colspan="4">Totals</td><td class="text-money">{{ money($rows->sum('expected')) }}</td><td class="text-money">{{ money($rows->sum('actual')) }}</td><td class="text-money">{{ money($rows->sum('arrears')) }}</td></tr></tfoot>
    </table>
</div></div>
@endsection
