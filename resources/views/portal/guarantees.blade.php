@extends('layouts.app')
@section('title', 'Guarantor Obligations')
@section('content')
<div class="row g-3 mb-3">
    @foreach([
        ['Currently guaranteeing', money($totals['guaranteed'], 0), $obligations->count() . ' loan(s)', 'secondary'],
        ['Current exposure', money($totals['exposure'], 0), 'falls as borrowers repay', 'primary'],
        ['On loans in arrears', money($totals['at_risk'], 0), 'more than 30 days late', $totals['at_risk'] > 0 ? 'danger' : 'success'],
        ['You can still guarantee', money($totals['capacity'], 0), 'of your KES ' . money($member->guaranteeLimit(), 0) . ' limit', 'success'],
    ] as [$l, $v, $sub, $c])
        <div class="col-6 col-lg-3"><div class="card stat h-100"><div class="card-body"><div class="label">{{ $l }}</div><div class="value text-{{ $c }}">{{ $v }}</div><small class="text-muted">{{ $sub }}</small></div></div></div>
    @endforeach
</div>

@if($member->guarantor_status !== 'eligible')
    <div class="alert alert-warning">You are currently <strong>{{ $member->guarantor_status }}</strong> as a guarantor{{ $member->guarantor_note ? ': ' . $member->guarantor_note : '' }}. Please contact the office.</div>
@elseif(! $member->guarantor_registered_at)
<div class="card mb-3 border-primary"><div class="card-body d-flex flex-wrap gap-3 align-items-center">
    <div class="flex-grow-1"><h6 class="mb-1"><i class="bi bi-person-check"></i> Register as a guarantor</h6>
        <p class="small mb-0 text-muted">Register so other members can ask you to guarantee their loans. You can guarantee up to <strong>KES {{ money($member->guaranteeLimit(), 0) }}</strong> in total,
        and you always decide whether to accept each request. If a borrower defaults, the SACCO may recover your share of the balance from your deposits.</p></div>
    <form method="POST" action="{{ route('portal.guarantor.register') }}" data-confirm="Register as a guarantor and accept the guarantor terms?">@csrf<button class="btn btn-primary">I agree, register me</button></form>
</div></div>
@endif

@if($requests->count())
<div class="card mb-3 border-warning"><div class="card-body">
    <h6 class="text-warning-emphasis"><i class="bi bi-bell"></i> Requests waiting for your answer</h6>
    <table class="table align-middle mb-0">
        @foreach($requests as $g)
            <tr><td>{{ $g->loan->member->full_name }}<br><small class="text-muted">{{ $g->loan->member->member_no }} · {{ $g->loan->product->name }} {{ $g->loan->loan_no }}</small></td>
                <td class="text-money">Loan KES {{ money($g->loan->amount_applied, 0) }}<br><strong>You: KES {{ money($g->amount, 0) }}</strong></td>
                <td class="text-end text-nowrap">
                    <button class="btn btn-sm btn-success" data-bs-toggle="collapse" data-bs-target="#sg{{ $g->id }}">Accept &amp; sign</button>
                    <button class="btn btn-sm btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#dg{{ $g->id }}">Decline</button>
                    @if($g->expires_at)<div class="small text-muted mt-1">Answer by {{ $g->expires_at->format('d M') }}</div>@endif
                </td></tr>
            <tr class="collapse" id="dg{{ $g->id }}"><td colspan="3">
                <form method="POST" action="{{ route('portal.guarantees.respond', $g) }}" class="d-flex gap-2 col-lg-8">@csrf<input type="hidden" name="accept" value="0">
                    <input name="reason" class="form-control form-control-sm" placeholder="Reason (optional, shared with the borrower's loan officer)">
                    <button class="btn btn-sm btn-danger">Decline</button></form>
            </td></tr>
            <tr class="collapse" id="sg{{ $g->id }}"><td colspan="3">
                <form method="POST" action="{{ route('portal.guarantees.sign', $g) }}" class="col-lg-8">@csrf
                    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="agree" value="1" id="ag{{ $g->id }}" required>
                        <label class="form-check-label small" for="ag{{ $g->id }}">I guarantee loan {{ $g->loan->loan_no }} of {{ $g->loan->member->full_name }} for KES {{ money($g->amount) }}. If the borrower defaults, the SACCO may recover my share from my deposits.</label></div>
                    @include('partials.signature-pad', ['name' => 'signature', 'height' => 140])
                    <button class="btn btn-success">Accept and sign</button>
                </form>
            </td></tr>
        @endforeach
    </table>
</div></div>
@endif

<div class="card mb-3"><div class="card-body">
    <h6>Loans I am guaranteeing</h6>
    <table class="table align-middle">
        <thead><tr><th>Borrower</th><th>Loan</th><th class="text-end">I guaranteed</th><th class="text-end">My current exposure</th><th>Borrower's repayment</th><th></th></tr></thead>
        @forelse($obligations as $o)
            @php($g = $o['g'])
            <tr><td>{{ $g->loan->member->full_name }}<br><small class="text-muted">{{ $g->loan->member->member_no }}</small></td>
                <td>{{ $g->loan->loan_no }}<br><small class="text-muted">{{ $g->loan->product->name }} · {{ $g->loan->status_label }}</small></td>
                <td class="text-money">{{ money($g->amount) }}</td>
                <td class="text-money fw-semibold">{{ money($o['exposure']) }}
                    @if($g->loan->status === 'disbursed' && (float) $g->loan->amount_approved > 0)
                        @php($repaid = 100 - (float) $g->loan->principal_balance / (float) $g->loan->amount_approved * 100)
                        <div class="progress mt-1" style="height:5px"><div class="progress-bar bg-success" style="width: {{ $repaid }}%"></div></div>
                        <small class="text-muted">{{ round($repaid) }}% repaid</small>
                    @endif</td>
                <td>@if($g->loan->status !== 'disbursed')<span class="badge text-bg-secondary">Not yet disbursed</span>
                    @elseif($o['days'] === 0)<span class="badge text-bg-success">Up to date</span>
                    @elseif($o['days'] <= 30)<span class="badge text-bg-warning">{{ $o['days'] }} days late</span>
                    @else<span class="badge text-bg-danger">{{ $o['days'] }} days late – please follow up</span>@endif</td>
                <td class="text-end">@if($g->release_requested_at)<span class="badge text-bg-warning">Release requested</span>
                    @else<button class="btn btn-sm btn-link p-0" data-bs-toggle="collapse" data-bs-target="#rr{{ $g->id }}">Ask to be released</button>@endif</td></tr>
            @unless($g->release_requested_at)
            <tr class="collapse" id="rr{{ $g->id }}"><td colspan="6">
                <form method="POST" action="{{ route('portal.guarantees.release', $g) }}" class="d-flex gap-2">@csrf
                    <input name="reason" class="form-control form-control-sm" placeholder="Why? e.g. I need my deposits for my own loan / I am leaving the SACCO" required>
                    <button class="btn btn-sm btn-outline-primary text-nowrap">Send request</button></form>
                <small class="text-muted">The borrower will be asked to find a replacement. You stay liable until the SACCO approves your release.</small>
            </td></tr>
            @endunless
        @empty <tr><td colspan="6" class="text-muted">You are not guaranteeing any running loans.</td></tr> @endforelse
    </table>
    <p class="small text-muted mb-0">Your exposure reduces automatically as the borrower repays principal, and you are released when the loan is cleared. If a borrower goes more than 90 days late, the SACCO may recover your share of the balance from your deposits.</p>
</div></div>

@if($history->count())
<div class="card"><div class="card-body small">
    <h6>Past guarantees</h6>
    <table class="table table-sm mb-0">@foreach($history as $g)<tr><td>{{ $g->loan->loan_no }}</td><td>{{ $g->loan->member->full_name }}</td><td class="text-money">{{ money($g->amount) }}</td><td>{{ $g->status_label }}</td><td>{{ $g->release_reason }}</td></tr>@endforeach</table>
</div></div>
@endif
@endsection
