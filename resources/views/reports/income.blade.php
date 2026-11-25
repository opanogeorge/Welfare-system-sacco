@extends('layouts.app')
@section('title', 'Income & Expenditure')
@section('content')
<form class="row g-2 mb-3 no-print">
    <div class="col-auto"><input type="date" name="from" value="{{ $from }}" class="form-control"></div>
    <div class="col-auto"><input type="date" name="to" value="{{ $to }}" class="form-control"></div>
    <div class="col-auto"><button class="btn btn-outline-secondary">Filter</button> <button type="button" onclick="print()" class="btn btn-outline-secondary"><i class="bi bi-printer"></i></button></div>
</form>
<div class="card col-lg-7"><div class="card-body">
    <h5 class="text-center">{{ sacco_name() }}</h5>
    <h6 class="text-center text-muted mb-3">Income &amp; Expenditure · {{ \Carbon\Carbon::parse($from)->format('d M Y') }} – {{ \Carbon\Carbon::parse($to)->format('d M Y') }}</h6>
    <table class="table table-sm">
        <tr class="table-light"><th colspan="2">INCOME</th></tr>
        @foreach($income as $k => $v)<tr><td class="ps-3">{{ $k }}</td><td class="text-money">{{ money($v) }}</td></tr>@endforeach
        <tr class="fw-bold"><td>Total income</td><td class="text-money">{{ money(array_sum($income)) }}</td></tr>
        <tr class="table-light"><th colspan="2">EXPENDITURE</th></tr>
        @forelse($expenses as $k => $v)<tr><td class="ps-3">{{ $k }}</td><td class="text-money">{{ money($v) }}</td></tr>@empty<tr><td class="ps-3 text-muted" colspan="2">None</td></tr>@endforelse
        <tr class="fw-bold"><td>Total expenditure</td><td class="text-money">{{ money($expenses->sum()) }}</td></tr>
        @php($surplus = array_sum($income) - $expenses->sum())
        <tr class="fw-bold fs-6 {{ $surplus >= 0 ? 'table-success' : 'table-danger' }}"><td>{{ $surplus >= 0 ? 'Surplus' : 'Deficit' }}</td><td class="text-money">{{ money($surplus) }}</td></tr>
    </table>
    <p class="small text-muted mb-0">Cash-basis management summary. Insurance collected is shown as income here; if you remit it to an insurer, record the remittance as an expense.</p>
</div></div>
@endsection
