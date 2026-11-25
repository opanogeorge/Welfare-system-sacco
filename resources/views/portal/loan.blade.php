@extends('layouts.app')
@section('title', "Loan {$loan->loan_no}")
@section('content')
<div class="card mb-3"><div class="card-body">
    <div class="d-flex"><h5>{{ $loan->product->name }} <span class="badge text-bg-{{ $loan->badge() }}">{{ $loan->status_label }}</span></h5>
        @if($loan->status === 'disbursed')<div class="ms-auto text-end"><small class="text-muted">Outstanding</small><div class="fs-4 fw-bold">KES {{ money($loan->outstanding) }}</div>
            @if($loan->arrears() > 0)<div class="small text-danger">Arrears KES {{ money($loan->arrears()) }}</div>@endif
            <a href="{{ route('portal.pay', ['to' => $loan->loan_no]) }}" class="btn btn-sm btn-success mt-1"><i class="bi bi-phone"></i> Pay now</a></div>@endif</div>
    <div class="row small g-2">
        <div class="col-6 col-md-3">Applied: <strong>{{ money($loan->amount_applied) }}</strong></div>
        <div class="col-6 col-md-3">Approved: <strong>{{ $loan->amount_approved ? money($loan->amount_approved) : '—' }}</strong></div>
        <div class="col-6 col-md-3">Rate: <strong>{{ $loan->interest_rate }}% ({{ \App\Models\Loan::METHODS[$loan->interest_method] ?? $loan->interest_method }})</strong></div>
        <div class="col-6 col-md-3">Period: <strong>{{ $loan->period }} months</strong></div>
    </div>
    <h6 class="mt-3 mb-0">Application progress</h6>
    @include('portal._loan-tracker', ['loan' => $loan])
    @if(in_array($loan->status, ['disbursed', 'closed', 'written_off']))
        <a href="{{ route('portal.loans.statement', $loan) }}" class="btn btn-sm btn-outline-secondary mt-2"><i class="bi bi-printer"></i> Loan statement &amp; repayment history</a>
    @endif
</div></div>
@php($signed = \App\Models\Signature::where('signable_type', \App\Models\Loan::class)->where('signable_id', $loan->id)->where('member_id', $loan->member_id)->exists())
<div class="card mb-3"><div class="card-body">
    <div class="d-flex"><h6><i class="bi bi-pen"></i> Loan agreement</h6><a href="{{ route('loans.agreement', $loan) }}" target="_blank" class="ms-auto small">View / download</a></div>
    @if($signed)<div class="text-success small"><i class="bi bi-check-circle"></i> You have signed this agreement.</div>
    @elseif(! in_array($loan->status, ['rejected', 'closed']))
        <form method="POST" action="{{ route('portal.loans.sign', $loan) }}" class="col-lg-7">@csrf
            <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="agree" value="1" id="agr" required>
                <label class="form-check-label small" for="agr">I have read the loan agreement and agree to its terms, including repayment by check-off and recovery from deposits on default.</label></div>
            @include('partials.signature-pad', ['name' => 'signature', 'height' => 140])
            <button class="btn btn-success">Sign agreement</button>
        </form>
    @endif
</div></div>
<div class="card mb-3"><div class="card-body">
    <h6>Guarantors</h6>
    @php($canEditG = in_array($loan->status, ['guarantors_pending', 'pending', 'appraised', 'approved', 'disbursed']))
    <table class="table table-sm mb-0 align-middle">@foreach($loan->guarantors as $g)
        <tr><td>{{ $g->member->full_name }}@if($g->release_requested_at)<br><small class="text-warning-emphasis">has asked to be released – please add a replacement</small>@endif</td>
            <td class="text-money">{{ money($g->amount) }}</td>
            <td><span class="badge text-bg-{{ $g->badge() }}">{{ $g->status_label }}</span>@if($g->status === 'pending' && $g->expires_at)<br><small class="text-muted">until {{ $g->expires_at->format('d/m') }}</small>@endif</td>
            <td class="text-end">@if($canEditG && (in_array($g->status, ['declined', 'expired']) || $g->release_requested_at) && ! $g->replaced_by_id)
                <button class="btn btn-sm btn-link p-0" data-bs-toggle="collapse" data-bs-target="#pr{{ $g->id }}">replace</button>@endif</td></tr>
        <tr class="collapse" id="pr{{ $g->id }}"><td colspan="4">@include('portal._guarantor-form', ['replace' => $g->id, 'amount' => $g->amount])</td></tr>
    @endforeach</table>
    @if($canEditG)
        <button class="btn btn-sm btn-outline-primary mt-2" data-bs-toggle="collapse" data-bs-target="#pAddG"><i class="bi bi-person-plus"></i> Add a guarantor</button>
        <div class="collapse mt-2" id="pAddG">@include('portal._guarantor-form', ['replace' => null, 'amount' => null])</div>
    @endif
</div></div>
@if($loan->schedules->count())
<div class="card mb-3"><div class="card-body"><h6>Repayment schedule</h6>@include('loans._schedule', ['rows' => $loan->schedules, 'live' => true])</div></div>
@endif
@if($loan->repayments->count())
<div class="card"><div class="card-body"><h6>Payments</h6>
    <table class="table table-sm mb-0">@foreach($loan->repayments as $r)<tr><td>{{ $r->paid_on->format('d/m/Y') }}</td><td>{{ $r->channel }} {{ $r->external_ref }}</td><td class="text-money">{{ money($r->amount) }}</td></tr>@endforeach</table>
</div></div>
@endif
@endsection
