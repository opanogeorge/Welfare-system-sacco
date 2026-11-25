@extends('layouts.app')
@section('title', 'Member Statement')
@section('content')
<form class="row g-2 mb-3 no-print">
    <div class="col-auto"><input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control"></div>
    <div class="col-auto"><input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control"></div>
    <div class="col-auto"><button class="btn btn-outline-secondary">Filter</button></div>
    <div class="col-auto"><button type="button" onclick="window.print()" class="btn btn-outline-success"><i class="bi bi-printer"></i> Print</button></div>
    <div class="col-auto"><a href="{{ isset($portal) ? route('portal.statement.pdf', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) : route('members.statement.pdf', [$member, 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}" class="btn btn-success"><i class="bi bi-file-pdf"></i> Download PDF</a></div>
</form>

<div class="card"><div class="card-body">
    <div class="d-flex justify-content-between border-bottom pb-3 mb-3">
        <div>
            <h4 class="mb-0">{{ sacco_name() }}</h4>
            <div class="small text-muted">{{ setting('sacco_address') }} {{ setting('sacco_phone') }}</div>
            <h6 class="mt-2">MEMBER STATEMENT · {{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}</h6>
        </div>
        <div class="text-end small">
            <strong>{{ $member->full_name }}</strong><br>Member No: {{ $member->member_no }}<br>ID: {{ $member->national_id }}<br>Printed: {{ now()->format('d/m/Y H:i') }}
        </div>
    </div>

    <div class="row g-2 mb-3">
        @foreach($member->accounts as $a)
            <div class="col"><div class="border rounded p-2 small"><div class="text-muted">{{ $a->type->name }}</div><strong>KES {{ money($a->balance) }}</strong></div></div>
        @endforeach
        <div class="col"><div class="border rounded p-2 small"><div class="text-muted">Loans outstanding</div><strong class="text-danger">KES {{ money($member->outstandingLoans()) }}</strong></div></div>
    </div>

    <table class="table table-sm table-striped">
        <thead class="table-light"><tr><th>Date</th><th>Reference</th><th>Account / Loan</th><th>Description</th><th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end">Balance</th></tr></thead>
        @forelse($rows as $t)
            <tr class="{{ $t->reversed ? 'text-muted' : '' }}">
                <td>{{ $t->txn_date->format('d/m/Y') }}</td><td class="small">{{ $t->reference }}</td>
                <td>{{ $t->account?->type->name ?? ($t->loan?->loan_no ?? '—') }}</td>
                <td>{{ $t->description ?? str_replace('_', ' ', $t->type) }} @if($t->external_ref)<span class="text-muted small">({{ $t->external_ref }})</span>@endif</td>
                <td class="text-money">{{ $t->direction === 'debit' ? money($t->amount) : '' }}</td>
                <td class="text-money">{{ $t->direction === 'credit' ? money($t->amount) : '' }}</td>
                <td class="text-money">{{ $t->balance_after !== null ? money($t->balance_after) : '' }}</td>
            </tr>
        @empty <tr><td colspan="7" class="text-muted text-center">No transactions in this period.</td></tr> @endforelse
    </table>

    @php($running = $member->loans->where('status', 'disbursed'))
    @if($running->count())
        <h6 class="mt-4">Running loans</h6>
        <table class="table table-sm">
            <thead class="table-light"><tr><th>Loan No</th><th>Product</th><th>Disbursed</th><th class="text-end">Principal</th><th class="text-end">Paid</th><th class="text-end">Outstanding</th></tr></thead>
            @foreach($running as $l)
                <tr><td>{{ $l->loan_no }}</td><td>{{ $l->product->name }}</td><td>{{ $l->disbursed_on?->format('d/m/Y') }}</td>
                    <td class="text-money">{{ money($l->amount_approved) }}</td><td class="text-money">{{ money($l->total_paid) }}</td><td class="text-money">{{ money($l->outstanding) }}</td></tr>
            @endforeach
        </table>
    @endif
</div></div>
@endsection
