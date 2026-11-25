@extends('layouts.app')
@section('title', "Loan Statement {$loan->loan_no}")
@section('content')
<div class="no-print mb-3"><button onclick="print()" class="btn btn-success"><i class="bi bi-printer"></i> Print / Save PDF</button> <a href="{{ request()->routeIs('portal.*') ? route('portal.loans.show', $loan) : route('loans.show', $loan) }}" class="btn btn-link">Back</a></div>
<div class="card"><div class="card-body">
    <div class="d-flex justify-content-between border-bottom pb-2 mb-3">
        <div><h5 class="mb-0">{{ sacco_name() }}</h5><div class="small text-muted">{{ setting('sacco_address') }} {{ setting('sacco_phone') }}</div><h6 class="mt-2">LOAN STATEMENT</h6></div>
        <div class="text-end small"><strong>{{ $loan->member->full_name }}</strong><br>{{ $loan->member->member_no }} · ID {{ $loan->member->national_id }}<br>Printed {{ now()->format('d/m/Y H:i') }}</div>
    </div>
    <div class="row small mb-3">
        <div class="col-3">Loan no: <strong>{{ $loan->loan_no }}</strong></div><div class="col-3">Product: <strong>{{ $loan->product->name }}</strong></div>
        <div class="col-3">Principal: <strong>{{ money($loan->amount_approved) }}</strong></div><div class="col-3">Rate: <strong>{{ $loan->interest_rate }}% {{ \App\Models\Loan::METHODS[$loan->interest_method] ?? $loan->interest_method }}</strong></div>
        <div class="col-3">Disbursed: <strong>{{ $loan->disbursed_on?->format('d/m/Y') }}</strong></div><div class="col-3">Maturity: <strong>{{ $loan->maturity_date?->format('d/m/Y') }}</strong></div>
        <div class="col-3">Total paid: <strong>{{ money($loan->total_paid) }}</strong></div><div class="col-3">Outstanding: <strong class="text-danger">{{ money($loan->outstanding) }}</strong></div>
    </div>
    <h6>Repayment history</h6>
    <table class="table table-sm">
        <thead class="table-light"><tr><th>Date</th><th>Reference</th><th>Channel</th><th class="text-end">Amount</th><th class="text-end">Penalty</th><th class="text-end">Interest</th><th class="text-end">Principal</th></tr></thead>
        @forelse($loan->repayments->sortBy('paid_on') as $r)
            <tr><td>{{ $r->paid_on->format('d/m/Y') }}</td><td>{{ $r->reference }} {{ $r->external_ref }}</td><td>{{ $r->channel }}</td><td class="text-money">{{ money($r->amount) }}</td>
                <td class="text-money">{{ money($r->penalty_paid) }}</td><td class="text-money">{{ money($r->interest_paid) }}</td><td class="text-money">{{ money($r->principal_paid) }}</td></tr>
        @empty <tr><td colspan="7" class="text-muted">No repayments yet.</td></tr> @endforelse
    </table>
    @if($loan->charges->count())
    <h6>Fines &amp; waivers</h6>
    <table class="table table-sm">
        @foreach($loan->charges->sortBy('created_at') as $c)
            <tr><td>{{ $c->created_at->format('d/m/Y') }}</td><td>{{ ucfirst($c->kind) }}: {{ str_replace('_', ' ', $c->category) }}</td><td>{{ $c->reason }}</td><td class="text-money">{{ $c->kind === 'waiver' ? '-' : '' }}{{ money($c->amount) }}</td></tr>
        @endforeach
    </table>
    @endif
    <h6>Schedule</h6>
    @include('loans._schedule', ['rows' => $loan->schedules, 'live' => true])
</div></div>
@endsection
