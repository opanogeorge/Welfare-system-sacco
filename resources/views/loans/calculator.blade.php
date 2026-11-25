@extends('layouts.app')
@section('title', 'Loan Calculator')
@section('content')
<div class="card mb-3"><div class="card-body">
    <form class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label small">Product (optional)</label>
            <select class="form-select" onchange="const o=this.selectedOptions[0]; if(o.dataset.rate){ rate.value=o.dataset.rate; method.value=o.dataset.method; }">
                <option value="">Custom</option>@foreach($products as $p)<option data-rate="{{ $p->interest_rate }}" data-method="{{ $p->interest_method }}">{{ $p->name }}</option>@endforeach
            </select></div>
        <div class="col-md-2"><label class="form-label small">Amount (KES)</label><input type="number" name="amount" value="{{ request('amount', 100000) }}" class="form-control" required></div>
        <div class="col-md-2"><label class="form-label small">Rate % p.a.</label><input type="number" step="0.01" id="rate" name="rate" value="{{ request('rate', 12) }}" class="form-control" required></div>
        <div class="col-md-2"><label class="form-label small">Months</label><input type="number" name="period" value="{{ request('period', 12) }}" class="form-control" required></div>
        <div class="col-md-2"><label class="form-label small">Method</label><select id="method" name="method" class="form-select">
            <option value="reducing" @selected(request('method') === 'reducing')>Reducing balance</option><option value="equal_principal" @selected(request('method') === 'equal_principal')>Reducing balance – equal principal</option><option value="flat" @selected(request('method') === 'flat')>Flat rate</option></select></div>
        <div class="col-md-1"><button class="btn btn-success w-100">Go</button></div>
    </form>
</div></div>
@if($result)
<div class="row g-3 mb-3">
    @foreach(['Monthly installment' => $result['installment'], 'Total interest' => $result['total_interest'], 'Total repayable' => $result['total_payable']] as $k => $v)
        <div class="col-md-4"><div class="card stat"><div class="card-body"><div class="label">{{ $k }}</div><div class="value">KES {{ money($v) }}</div></div></div></div>
    @endforeach
</div>
<div class="card"><div class="card-body">@include('loans._schedule', ['rows' => $result['rows'], 'live' => false])</div></div>
@endif
@endsection
