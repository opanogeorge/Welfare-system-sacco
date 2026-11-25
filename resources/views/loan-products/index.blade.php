@extends('layouts.app')
@section('title', 'Loan Products')
@section('content')
<div class="card"><div class="card-body">
    <div class="d-flex mb-3"><a href="{{ route('loan-products.create') }}" class="btn btn-success ms-auto"><i class="bi bi-plus"></i> New product</a></div>
    <div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead><tr><th>Code</th><th>Name</th><th>Rate</th><th>Period</th><th class="text-end">Range (KES)</th><th>× Deposits</th><th>Guarantors</th><th>Fees</th><th>Running</th><th>Status</th><th></th></tr></thead>
        @foreach($products as $p)
            <tr><td>{{ $p->code }}</td><td>{{ $p->name }}</td><td>{{ $p->interest_rate }}% {{ \App\Models\Loan::METHODS[$p->interest_method] ?? $p->interest_method }}@if($p->grace_period_months)<div class="small text-muted">{{ $p->grace_period_months }} mo grace</div>@endif
                    <div class="small text-muted">@if($p->collateral_required)collateral · @endif{{ $p->allow_top_up ? 'top-ups' : 'no top-ups' }} · max {{ $p->max_active_per_member }} running</div></td><td>{{ $p->min_period }}–{{ $p->max_period }} mo</td>
                <td class="text-money">{{ money($p->min_amount, 0) }} – {{ money($p->max_amount, 0) }}</td><td>{{ $p->deposit_multiplier }}×</td><td>{{ $p->min_guarantors }}</td>
                <td class="small">Proc {{ $p->processing_fee_rate }}% · Ins {{ $p->insurance_rate }}% · Pen {{ $p->penalty_rate }}%</td><td>{{ $p->running }}</td>
                <td><span class="badge text-bg-{{ $p->is_active ? 'success' : 'secondary' }}">{{ $p->is_active ? 'Active' : 'Inactive' }}</span></td>
                <td><a href="{{ route('loan-products.edit', $p) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a></td></tr>
        @endforeach
    </table>
    </div>
</div></div>
@endsection
