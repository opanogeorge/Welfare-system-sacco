@extends('layouts.app')
@section('title', 'My Account')
@section('content')
<h5 class="mb-3">Hello {{ $member->first_name }} 👋 <small class="text-muted fs-6">Member No {{ $member->member_no }}</small></h5>
<div class="d-flex flex-wrap gap-2 mb-3">
    <a href="{{ route('portal.pay') }}" class="btn btn-sm btn-success"><i class="bi bi-phone"></i> Deposit / pay</a>
    <a href="{{ route('portal.loans.apply') }}" class="btn btn-sm btn-success"><i class="bi bi-cash-coin"></i> Apply for loan</a>
    <a href="{{ route('portal.withdrawals') }}" class="btn btn-sm btn-outline-success"><i class="bi bi-wallet2"></i> Request withdrawal</a>
    <a href="{{ route('portal.statement.pdf') }}" class="btn btn-sm btn-outline-success"><i class="bi bi-file-pdf"></i> Download statement</a>
    <a href="{{ route('portal.contributions') }}" class="btn btn-sm btn-outline-success"><i class="bi bi-calendar-check"></i> Contributions</a>
    <a href="{{ route('portal.tickets') }}" class="btn btn-sm btn-outline-success"><i class="bi bi-chat-dots"></i> Contact us</a>
</div>

@if($pendingGuarantees)
    <div class="alert alert-warning d-flex align-items-center"><i class="bi bi-bell me-2"></i>You have {{ $pendingGuarantees }} guarantee request(s) waiting for your response.
        <a href="{{ route('portal.guarantees') }}" class="btn btn-sm btn-warning ms-auto">Respond</a></div>
@endif

@php($inProgress = $member->loans()->with('product', 'guarantors')->whereIn('status', ['guarantors_pending', 'pending', 'appraised', 'approved'])->latest()->get())
@foreach($inProgress as $app)
    <div class="card mb-3 border-start border-4 border-warning"><div class="card-body py-2">
        <div class="d-flex align-items-center"><strong>{{ $app->product->name }} application · KES {{ money($app->amount_applied, 0) }}</strong>
            <a href="{{ route('portal.loans.show', $app) }}" class="ms-auto small">Details</a></div>
        @include('portal._loan-tracker', ['loan' => $app])
    </div></div>
@endforeach
<div class="row g-3 mb-3">
    @foreach($member->accounts as $a)
        <div class="col-6 col-lg-3"><div class="card stat h-100"><div class="card-body"><div class="label">{{ $a->type->name }}</div><div class="value">{{ money($a->balance) }}</div></div></div></div>
    @endforeach
    <div class="col-6 col-lg-3"><div class="card stat h-100"><div class="card-body"><div class="label">Loan balance</div><div class="value text-danger">{{ money($loans->sum('outstanding')) }}</div></div></div></div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100"><div class="card-body">
            <div class="d-flex mb-2"><h6 class="mb-0">My running loans</h6><a href="{{ route('portal.loans.apply') }}" class="btn btn-sm btn-success ms-auto">Apply for a loan</a></div>
            @if($nextDue)
                <div class="alert alert-info small py-2">Next installment: <strong>KES {{ money($nextDue->balance()) }}</strong> due {{ $nextDue->due_date->format('d M Y') }}
                    · Paybill {{ config('sacco.mpesa.paybill') }}, Account <strong>{{ $nextDue->loan->loan_no }}</strong></div>
            @endif
            <table class="table table-sm mb-0">
                @forelse($loans as $l)
                    <tr><td><a href="{{ route('portal.loans.show', $l) }}">{{ $l->loan_no }}</a><br><small class="text-muted">{{ $l->product->name }}</small></td>
                        <td class="text-money">{{ money($l->outstanding) }}</td>
                        <td>@if($l->daysInArrears())<span class="badge text-bg-danger">In arrears</span>@else<span class="badge text-bg-success">Up to date</span>@endif</td>
                        <td class="text-end"><a href="{{ route('portal.pay', ['to' => $l->loan_no]) }}" class="btn btn-sm btn-outline-success py-0">Pay</a></td></tr>
                @empty <tr><td class="text-muted">No running loans.</td></tr> @endforelse
            </table>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100"><div class="card-body">
            <div class="d-flex mb-2"><h6 class="mb-0">Recent transactions</h6><a href="{{ route('portal.statement') }}" class="ms-auto small">Full statement</a></div>
            <table class="table table-sm mb-0">
                @foreach($recent as $t)
                    <tr><td class="small">{{ $t->txn_date->format('d M') }}</td><td>{{ $t->account?->type->name ?? 'Loan' }}<br><small class="text-muted">{{ str_replace('_', ' ', $t->type) }}</small></td>
                        <td class="text-money {{ $t->direction === 'credit' ? 'text-success' : 'text-danger' }}">{{ $t->direction === 'debit' ? '-' : '' }}{{ money($t->amount) }}</td></tr>
                @endforeach
            </table>
        </div></div>
    </div>
</div>
<p class="small text-muted mt-3">Deposit via M-Pesa Paybill <strong>{{ config('sacco.mpesa.paybill') }}</strong>, account <strong>{{ $member->member_no }}</strong> (deposits), <strong>{{ $member->member_no }}-SHR</strong> (shares) or <strong>{{ $member->member_no }}-SAV</strong> (savings).</p>
@endsection
