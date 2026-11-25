@extends('layouts.app')
@section('title', 'Receipt')
@section('content')
<div class="no-print mb-3 d-flex gap-2">
    <button onclick="window.print()" class="btn btn-success"><i class="bi bi-printer"></i> Print</button>
    <a href="{{ route('transactions.create', ['member' => $transaction->member_id]) }}" class="btn btn-outline-secondary">New transaction for this member</a>
    <a href="{{ route('transactions.create') }}" class="btn btn-outline-secondary">New member</a>
</div>
<div class="card mx-auto" style="max-width: 380px; font-family: monospace;"><div class="card-body">
    <div class="text-center border-bottom pb-2 mb-2">
        <strong>{{ strtoupper(sacco_name()) }}</strong><br><small>{{ setting('sacco_address') }}<br>{{ setting('sacco_phone') }}</small><br>
        <strong>{{ strtoupper(str_replace('_', ' ', $transaction->type)) }} RECEIPT</strong>
    </div>
    <table class="w-100 small">
        <tr><td>Receipt No</td><td class="text-end">{{ $transaction->reference }}</td></tr>
        <tr><td>Date</td><td class="text-end">{{ $transaction->txn_date->format('d/m/Y') }} {{ $transaction->created_at->format('H:i') }}</td></tr>
        <tr><td>Member</td><td class="text-end">{{ $transaction->member->member_no }}</td></tr>
        <tr><td colspan="2">{{ $transaction->member->full_name }}</td></tr>
        <tr><td>Account</td><td class="text-end">{{ $transaction->account?->type->name }}</td></tr>
        <tr><td>Channel</td><td class="text-end">{{ strtoupper($transaction->channel) }} {{ $transaction->external_ref }}</td></tr>
        <tr class="border-top"><td><strong>AMOUNT</strong></td><td class="text-end"><strong>KES {{ money($transaction->amount) }}</strong></td></tr>
        <tr><td>Balance</td><td class="text-end">KES {{ money($transaction->balance_after) }}</td></tr>
        <tr><td>Served by</td><td class="text-end">{{ $transaction->poster?->name }}</td></tr>
    </table>
    <div class="text-center small border-top mt-2 pt-2">Thank you for saving with us.</div>
</div></div>
@endsection
