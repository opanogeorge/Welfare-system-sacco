@extends('layouts.app')
@section('title', 'Transactions')
@section('content')
<div class="card"><div class="card-body">
    <form class="row g-2 mb-3">
        <div class="col-md-3"><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Ref, M-Pesa code, member"></div>
        <div class="col-md-2"><select name="type" class="form-select"><option value="">All types</option>
            @foreach(['deposit', 'withdrawal', 'loan_disbursement', 'loan_repayment', 'fee', 'penalty', 'dividend', 'transfer_in', 'transfer_out', 'reversal'] as $t)<option value="{{ $t }}" @selected(request('type') === $t)>{{ str_replace('_', ' ', $t) }}</option>@endforeach</select></div>
        <div class="col-md-2"><select name="channel" class="form-select"><option value="">All channels</option>
            @foreach(['cash', 'mpesa', 'bank', 'cheque', 'payroll', 'internal'] as $c)<option @selected(request('channel') === $c)>{{ $c }}</option>@endforeach</select></div>
        <div class="col-md-2"><input type="date" name="from" value="{{ request('from') }}" class="form-control"></div>
        <div class="col-md-2"><input type="date" name="to" value="{{ request('to') }}" class="form-control"></div>
        <div class="col-md-1"><button class="btn btn-outline-secondary w-100"><i class="bi bi-search"></i></button></div>
    </form>
    <div class="d-flex gap-4 mb-2 small"><span>Total credits: <strong class="text-success">{{ money($totals->cr) }}</strong></span><span>Total debits: <strong class="text-danger">{{ money($totals->dr) }}</strong></span></div>
    <div class="table-responsive">
    <table class="table table-sm table-hover align-middle">
        <thead><tr><th>Date</th><th>Reference</th><th>Member</th><th>Account</th><th>Type</th><th>Channel</th><th class="text-end">Amount</th><th>By</th><th></th></tr></thead>
        @foreach($transactions as $t)
            <tr class="{{ $t->reversed ? 'text-muted text-decoration-line-through' : '' }}">
                <td>{{ $t->txn_date->format('d/m/Y') }}</td>
                <td class="small">{{ $t->reference }}@if($t->external_ref)<br><span class="text-muted">{{ $t->external_ref }}</span>@endif</td>
                <td><a href="{{ route('members.show', $t->member_id) }}">{{ $t->member->member_no }}</a> {{ $t->member->full_name }}</td>
                <td>{{ $t->account?->type->code ?? 'LOAN' }}</td>
                <td>{{ str_replace('_', ' ', $t->type) }}</td><td>{{ $t->channel }}</td>
                <td class="text-money {{ $t->direction === 'credit' ? 'text-success' : 'text-danger' }}">{{ $t->direction === 'debit' ? '-' : '' }}{{ money($t->amount) }}</td>
                <td class="small">{{ $t->poster?->name }}</td>
                <td class="text-nowrap">
                    @if($t->member_account_id)<a href="{{ route('transactions.receipt', $t) }}" class="btn btn-sm btn-link p-0" title="Receipt"><i class="bi bi-receipt"></i></a>@endif
                    @if($t->member_account_id && ! $t->reversed && $t->type !== 'reversal' && can_route('transactions.reverse'))
                        <form method="POST" action="{{ route('transactions.reverse', $t) }}" class="d-inline" onsubmit="const r = prompt('Reason for reversal?'); if (!r) return false; this.reason.value = r;">
                            @csrf <input type="hidden" name="reason"><button class="btn btn-sm btn-link text-danger p-0" title="Reverse"><i class="bi bi-arrow-counterclockwise"></i></button></form>
                    @endif
                </td>
            </tr>
        @endforeach
    </table>
    </div>
    {{ $transactions->links() }}
</div></div>
@endsection
