@extends('layouts.app')
@section('title', "Exit & Clearance · {$member->member_no}")
@section('content')
<div class="row g-3">
<div class="col-lg-6"><div class="card"><div class="card-body">
    <h5>{{ $member->full_name }}</h5>
    <table class="table">
        <tr><td>Member deposits (BOSA)</td><td class="text-money">{{ money($p['deposits']) }}</td></tr>
        <tr><td>Savings (FOSA)</td><td class="text-money">{{ money($p['savings']) }}</td></tr>
        <tr><td>Less: loans outstanding (offset)</td><td class="text-money text-danger">({{ money($p['loans']) }})</td></tr>
        <tr class="fw-bold table-success"><td>Refund payable</td><td class="text-money">{{ money($p['refund']) }}</td></tr>
        <tr><td>Share capital (not refundable – transfer to another member)</td><td class="text-money">{{ money($p['shares']) }}</td></tr>
        <tr><td>Current guarantee exposure</td><td class="text-money {{ $p['guarantees'] > 0 ? 'text-danger' : '' }}">{{ money($p['guarantees']) }}</td></tr>
    </table>
    @if($p['issues'])
        <div class="alert alert-danger"><strong>Cannot clear yet:</strong><ul class="mb-0">@foreach($p['issues'] as $i)<li>{{ $i }}</li>@endforeach</ul></div>
    @elseif($member->status !== 'exited' && auth()->user()->hasRole('manager'))
        <form method="POST" data-confirm="Process exit and post the refund? This cannot be undone.">@csrf
            <div class="row g-2 mb-2">
                <div class="col-md-4"><select name="channel" class="form-select"><option value="bank">Bank transfer</option><option value="mpesa">M-Pesa</option><option value="cheque">Cheque</option><option value="cash">Cash</option></select></div>
                <div class="col-md-8"><input name="reference" class="form-control" placeholder="Payment reference"></div>
            </div>
            <input name="reason" class="form-control mb-2" placeholder="Reason for exit (resignation, retirement, death, expulsion…)" required>
            <button class="btn btn-danger">Process exit &amp; refund KES {{ money($p['refund']) }}</button>
        </form>
        <p class="small text-muted mt-2 mb-0">Most by-laws require a notice period (often 60 days) before refunding deposits. Process the exit once that has passed.</p>
    @endif
</div></div></div>
<div class="col-lg-6">
    @if($p['shares'] > 0 && auth()->user()->hasRole('manager'))
    <div class="card"><div class="card-body">
        <h6>Transfer share capital</h6>
        <form method="POST" action="{{ route('members.transfer-shares', $member) }}">@csrf
            <select name="to_member_id" class="member-select" required></select>
            <input type="number" step="0.01" name="amount" value="{{ $p['shares'] }}" class="form-control my-2" required>
            <button class="btn btn-outline-success w-100">Transfer shares</button>
        </form>
        <p class="small text-muted mt-2 mb-0">The buying member pays the exiting member directly (or via the SACCO); this records the change of ownership.</p>
    </div></div>
    @endif
</div>
</div>
@endsection
