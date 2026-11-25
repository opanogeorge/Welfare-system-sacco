@extends('layouts.app')
@section('title', 'My Loans')
@section('content')
<div class="card"><div class="card-body">
    <div class="d-flex mb-3"><a href="{{ route('portal.loans.apply') }}" class="btn btn-success ms-auto"><i class="bi bi-plus"></i> Apply for a loan</a></div>
    <table class="table table-hover">
        <thead><tr><th>Loan No</th><th>Product</th><th>Applied</th><th class="text-end">Amount</th><th class="text-end">Balance</th><th>Status</th><th>Progress</th></tr></thead>
        @forelse($loans as $l)
            <tr><td><a href="{{ route('portal.loans.show', $l) }}">{{ $l->loan_no }}</a></td><td>{{ $l->product->name }}</td><td>{{ $l->applied_on->format('d/m/Y') }}</td>
                <td class="text-money">{{ money($l->amount_approved ?? $l->amount_applied) }}</td><td class="text-money">{{ $l->status === 'disbursed' ? money($l->outstanding) : '—' }}</td>
                <td><span class="badge text-bg-{{ $l->badge() }}">{{ $l->status_label }}</span></td>
                <td>@include('portal._loan-tracker', ['loan' => $l, 'compact' => true])</td></tr>
        @empty <tr><td colspan="7" class="text-muted">You have not applied for any loans yet.</td></tr> @endforelse
    </table>
</div></div>
@endsection
