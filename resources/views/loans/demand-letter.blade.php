@extends('layouts.app')
@section('title', "Demand Letter {$loan->loan_no}")
@section('content')
@php
    $arrears = $loan->status === 'disbursed' ? $loan->arrears() : $loan->outstanding;
    $deadline = today()->addDays($days);
    $guarantors = $loan->guarantors->where('status', 'accepted');
@endphp
<div class="no-print mb-3 d-flex gap-2">
    <button onclick="print()" class="btn btn-success"><i class="bi bi-printer"></i> Print / Save PDF</button>
    @if(auth()->user()->hasRole('manager', 'loan_officer'))
    <form method="POST" action="{{ route('loans.recovery-action', $loan) }}">@csrf<input type="hidden" name="action" value="demand_letter"><input type="hidden" name="notes" value="Demand letter printed; {{ $days }} days notice to {{ $deadline->format('d/m/Y') }}">
        <button class="btn btn-outline-primary">Log as issued</button></form>
    @endif
    <a href="{{ route('loans.show', $loan) }}" class="btn btn-link">Back</a>
</div>
<div class="card"><div class="card-body p-5" style="max-width: 800px; margin: auto">
    <div class="text-center border-bottom pb-3 mb-4">
        <h4 class="mb-0">{{ sacco_name() }}</h4>
        <div class="small text-muted">{{ setting('sacco_address') }} · {{ setting('sacco_phone') }} · {{ setting('sacco_email') }}</div>
    </div>
    <p>Ref: {{ $loan->loan_no }}/DL/{{ now()->format('Ymd') }}<br>Date: {{ now()->format('d F Y') }}</p>
    <p><strong>{{ $loan->member->full_name }}</strong><br>Member no. {{ $loan->member->member_no }}<br>{{ $loan->member->address ?? '' }} {{ $loan->member->phone }}</p>
    <p class="fw-bold text-decoration-underline">RE: DEMAND FOR PAYMENT OF OVERDUE LOAN {{ $loan->loan_no }}</p>
    <p>Our records show that your {{ $loan->product->name }} of KES {{ money($loan->amount_approved) }}, disbursed on {{ $loan->disbursed_on?->format('d F Y') }}, is in arrears.</p>
    <table class="table table-sm table-bordered w-auto">
        <tr><td>Amount in arrears</td><td class="text-end fw-bold">KES {{ money($arrears) }}</td></tr>
        @if($loan->status === 'disbursed')<tr><td>Days in arrears</td><td class="text-end">{{ $loan->daysInArrears() }}</td></tr>@endif
        <tr><td>Penalties included</td><td class="text-end">KES {{ money($loan->penalty_balance) }}</td></tr>
        <tr><td>Total outstanding balance</td><td class="text-end fw-bold">KES {{ money($loan->outstanding) }}</td></tr>
    </table>
    <p>We hereby demand that you pay the arrears of <strong>KES {{ money($arrears) }}</strong> within <strong>{{ $days }} days</strong> of this letter, that is on or before <strong>{{ $deadline->format('d F Y') }}</strong>.</p>
    <p>You can pay at any of our offices, or through M-Pesa Paybill <strong>{{ config('sacco.mpesa.paybill') ?: '________' }}</strong>, account number <strong>{{ $loan->loan_no }}</strong>.</p>
    <p>If you do not pay or agree a repayment plan with us by that date, the SACCO will, without further notice and in line with the loan agreement and by-laws:</p>
    <ol>
        <li>offset the balance against your deposits and other savings with the SACCO;</li>
        <li>recover the balance from your guarantors, as listed below;</li>
        <li>realise any security held, and/or take legal action, at your cost; and</li>
        <li>report the default to licensed credit reference bureaus.</li>
    </ol>
    <p>If you have already paid, please ignore this letter and share the payment details with us. If you are facing difficulties, contact us immediately to discuss rescheduling.</p>
    <p class="mt-5">Yours faithfully,</p>
    <p class="mt-5">______________________<br>{{ auth()->user()->name }}<br>For: {{ sacco_name() }}</p>
    @if($guarantors->count())
        <p class="small mt-4 mb-1"><strong>cc. Guarantors:</strong></p>
        <ul class="small">@foreach($guarantors as $g)<li>{{ $g->member->full_name }} ({{ $g->member->member_no }}) · guaranteed KES {{ money($g->amount) }}</li>@endforeach</ul>
    @endif
</div></div>
@endsection
