@extends('layouts.app')
@section('title', 'Statement of Financial Position')
@section('content')
@include('accounting._nav')
<div class="card col-xl-8"><div class="card-body">
    @include('accounting._period', ['heading' => 'STATEMENT OF FINANCIAL POSITION'])
    <table class="table table-sm">
        <tr class="table-light"><th colspan="2">ASSETS</th></tr>
        @foreach($bs['assets'] as $r)<tr><td class="ps-3">{{ $r['account']->name }}</td><td class="text-money">{{ money($r['amount']) }}</td></tr>@endforeach
        <tr class="fw-bold"><td>TOTAL ASSETS</td><td class="text-money">{{ money($bs['total_assets']) }}</td></tr>
        <tr class="table-light"><th colspan="2">LIABILITIES</th></tr>
        @foreach($bs['liabilities'] as $r)<tr><td class="ps-3">{{ $r['account']->name }}</td><td class="text-money">{{ money($r['amount']) }}</td></tr>@endforeach
        <tr class="fw-bold"><td>Total liabilities</td><td class="text-money">{{ money($bs['liabilities']->sum('amount')) }}</td></tr>
        <tr class="table-light"><th colspan="2">EQUITY</th></tr>
        @foreach($bs['equity'] as $r)<tr><td class="ps-3">{{ $r['account']->name }}</td><td class="text-money">{{ money($r['amount']) }}</td></tr>@endforeach
        <tr><td class="ps-3">Current period surplus (unappropriated)</td><td class="text-money">{{ money($bs['current_surplus']) }}</td></tr>
        <tr class="fw-bold"><td>Total equity</td><td class="text-money">{{ money($bs['equity']->sum('amount') + $bs['current_surplus']) }}</td></tr>
        <tr class="fw-bold border-top"><td>TOTAL LIABILITIES &amp; EQUITY</td><td class="text-money">{{ money($bs['total_le']) }}</td></tr>
    </table>
    @if(abs($bs['total_assets'] - $bs['total_le']) > 0.01)<div class="alert alert-danger">Out of balance by {{ money($bs['total_assets'] - $bs['total_le']) }}.</div>@endif
</div></div>
@endsection
