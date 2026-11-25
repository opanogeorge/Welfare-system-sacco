@extends('layouts.app')
@section('title', 'Statement of Comprehensive Income')
@section('content')
@include('accounting._nav')
<div class="card col-xl-8"><div class="card-body">
    @include('accounting._period', ['heading' => 'STATEMENT OF COMPREHENSIVE INCOME'])
    <table class="table table-sm">
        <tr class="table-light"><th colspan="2">INCOME</th></tr>
        @foreach($pl['income'] as $r)<tr><td class="ps-3">{{ $r['account']->name }}</td><td class="text-money">{{ money($r['amount']) }}</td></tr>@endforeach
        <tr class="fw-bold"><td>Total income</td><td class="text-money">{{ money($pl['total_income']) }}</td></tr>
        <tr class="table-light"><th colspan="2">EXPENDITURE</th></tr>
        @foreach($pl['expenses'] as $r)<tr><td class="ps-3">{{ $r['account']->name }}</td><td class="text-money">{{ money($r['amount']) }}</td></tr>@endforeach
        <tr class="fw-bold"><td>Total expenditure</td><td class="text-money">{{ money($pl['total_expenses']) }}</td></tr>
        <tr class="fw-bold fs-6 {{ $pl['surplus'] >= 0 ? 'table-success' : 'table-danger' }}"><td>{{ $pl['surplus'] >= 0 ? 'SURPLUS' : 'DEFICIT' }} FOR THE PERIOD</td><td class="text-money">{{ money($pl['surplus']) }}</td></tr>
    </table>
    <p class="small text-muted mb-0">Before dividends and interest on member deposits, which are appropriations of the surplus. Year-end closing entries are excluded so the year's figures remain visible after closing.</p>
</div></div>
@endsection
