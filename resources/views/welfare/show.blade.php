@extends('layouts.app')
@section('title', $e->event_no . ' · ' . $e->title)
@section('content')
@php
    $u = auth()->user();
    $paid = $summary->where('status', 'paid'); $due = $summary->where('status', 'due');
    $dueCount = (int) $due->sum('n'); $dueTotal = (float) $due->sum('total');
    $pct = $e->target_amount > 0 ? min(100, round($e->raised / $e->target_amount * 100)) : null;
@endphp
<div class="row g-3">
<div class="col-lg-8">
    <div class="card mb-3"><div class="card-body">
        <div class="d-flex align-items-start gap-3">
            <div class="fs-1 text-primary"><i class="bi bi-{{ $e->icon() }}"></i></div>
            <div class="flex-grow-1">
                <h5 class="mb-0">{{ $e->title }} <span class="badge text-bg-{{ $e->badge() }} align-middle">{{ $e->status_label }}</span></h5>
                <div class="small text-muted">{{ $e->event_no }} · {{ $e->type_label }}@if($e->relationship) ({{ \App\Models\WelfareEvent::RELATIONSHIPS[$e->relationship] ?? $e->relationship }})@endif · {{ $e->event_date?->format('d M Y') }}</div>
                <div class="mt-1">For <a href="{{ route('members.show', $e->member) }}">{{ $e->member->member_no }} · {{ $e->member->full_name }}</a> · {{ $e->member->phone }}</div>
            </div>
        </div>
        @if($e->details)<p class="mt-3 mb-0">{!! nl2br(e($e->details)) !!}</p>@endif
        @if($e->document)<a href="{{ route('welfare.document', $e) }}" class="small"><i class="bi bi-paperclip"></i> Supporting document</a>@endif
        <div class="row g-2 mt-3 text-center">
            <div class="col-6 col-md-3"><div class="border rounded p-2"><div class="small text-muted">Raised from members</div><div class="fs-5 fw-bold">{{ money($e->raised, 0) }}</div><div class="small text-muted">{{ (int) $paid->sum('n') }} contributions</div></div></div>
            <div class="col-6 col-md-3"><div class="border rounded p-2"><div class="small text-muted">Levies unpaid</div><div class="fs-5 fw-bold text-danger">{{ money($dueTotal, 0) }}</div><div class="small text-muted">{{ $dueCount }} members</div></div></div>
            <div class="col-6 col-md-3"><div class="border rounded p-2"><div class="small text-muted">Fund benefit</div><div class="fs-5 fw-bold">{{ money($e->fund_contribution, 0) }}</div></div></div>
            <div class="col-6 col-md-3"><div class="border rounded p-2"><div class="small text-muted">Paid out / due to member</div><div class="fs-5 fw-bold">{{ money($e->paid_out, 0) }}</div><div class="small text-muted">balance {{ money($e->payable(), 0) }}</div></div></div>
        </div>
        @if($pct !== null)<div class="progress mt-3" style="height:8px"><div class="progress-bar" style="width: {{ $pct }}%"></div></div><div class="small text-muted">{{ $pct }}% of KES {{ money($e->target_amount, 0) }} target</div>@endif
        <div class="small text-muted mt-2">
            {{ ['voluntary' => 'Voluntary contributions', 'levy' => 'Fixed levy', 'both' => 'Levy + voluntary'][$e->mode] ?? '' }}
            @if($e->levy_amount > 0) · KES {{ money($e->levy_amount, 0) }} per member, {{ ['SAV' => 'deducted from savings', 'DEP' => 'deducted from deposits', 'none' => 'paid by members'][$e->levy_source] ?? '' }}@endif
            @if($e->closes_on) · closes {{ $e->closes_on->format('d M Y') }}@endif
            · M-Pesa account: <code>member no.-W{{ $e->id }}</code>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-3">
            @if(in_array($e->status, ['draft', 'requested']))
                <a href="{{ route('welfare.edit', $e) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i> Edit</a>
            @endif
            @if($e->status === 'open')
                @if($dueCount && $e->levy_source !== 'none' && $u->hasRole('manager', 'accountant'))
                    <form method="POST" action="{{ route('welfare.retry', $e) }}">@csrf<button class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-repeat"></i> Retry deductions</button></form>
                @endif
                @if($dueCount)<form method="POST" action="{{ route('welfare.remind', $e) }}" data-confirm="SMS the {{ $dueCount }} member(s) who haven't paid?">@csrf<button class="btn btn-sm btn-outline-warning"><i class="bi bi-bell"></i> Remind unpaid ({{ $dueCount }})</button></form>@endif
                @if($u->hasRole('manager'))
                    <form method="POST" action="{{ route('welfare.announce', $e) }}" data-confirm="Send the announcement SMS to all members again?">@csrf<button class="btn btn-sm btn-outline-secondary"><i class="bi bi-megaphone"></i> Re-announce</button></form>
                    <form method="POST" action="{{ route('welfare.close', $e) }}" data-confirm="Close this event for contributions?">@csrf<button class="btn btn-sm btn-outline-dark">Close contributions</button></form>
                @endif
            @endif
        </div>
    </div></div>

    <div class="card"><div class="card-body">
        <h6>Contributions</h6>
        <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead><tr><th>Member</th><th>Kind</th><th class="text-end">Amount</th><th>How</th><th>Ref</th><th>Date</th><th>Status</th><th></th></tr></thead>
            @forelse($contributions as $c)
                <tr><td>{{ $c->member->member_no }} {{ $c->member->full_name }}</td><td class="small">{{ $c->kind }}</td><td class="text-money">{{ money($c->amount) }}</td>
                    <td class="small">{{ $c->channel }}</td><td class="small">{{ $c->reference }}</td><td class="small">{{ $c->paid_at?->format('d/m/Y') }}</td>
                    <td><span class="badge text-bg-{{ ['paid' => 'success', 'due' => 'danger', 'waived' => 'secondary'][$c->status] }}">{{ $c->status }}</span></td>
                    <td>@if($c->status === 'due' && $u->hasRole('manager'))<form method="POST" action="{{ route('welfare.waive', $c) }}" data-confirm="Waive this member's levy (e.g. hardship)?">@csrf<button class="btn btn-sm btn-link p-0">waive</button></form>@endif</td></tr>
            @empty<tr><td colspan="8" class="text-muted">No contributions yet.</td></tr>@endforelse
        </table>
        </div>
        {{ $contributions->links() }}
    </div></div>
</div>

<div class="col-lg-4">
    @if(in_array($e->status, ['draft', 'requested']) && $u->hasRole('manager'))
        <div class="card mb-3 border-primary"><div class="card-body">
            <h6>{{ $e->status === 'requested' ? 'Member request: review' : 'Approve & open' }}</h6>
            <p class="small">Opening the event {{ $e->mode !== 'voluntary' && $e->levy_amount > 0 ? 'charges KES ' . money($e->levy_amount, 0) . ' to every active member and ' : '' }}lets members contribute.</p>
            @if($e->status === 'requested')<p class="small text-warning">Check the details, set the levy and benefit under <a href="{{ route('welfare.edit', $e) }}">Edit</a> first.</p>@endif
            <form method="POST" action="{{ route('welfare.open', $e) }}" data-confirm="Open this event{{ $e->mode !== 'voluntary' && $e->levy_amount > 0 ? ' and charge the levy' : '' }}?">@csrf
                <div class="form-check mb-2"><input type="hidden" name="announce" value="0"><input type="checkbox" name="announce" value="1" class="form-check-input" id="ann" checked><label class="form-check-label small" for="ann">SMS all members</label></div>
                <button class="btn btn-primary w-100">Approve &amp; open</button></form>
            <form method="POST" action="{{ route('welfare.reject', $e) }}" class="mt-2">@csrf
                <div class="input-group input-group-sm"><input name="reason" class="form-control" placeholder="Reason for rejecting" required><button class="btn btn-outline-danger">Reject</button></div></form>
        </div></div>
        <div class="card mb-3"><div class="card-body small"><h6>Announcement preview</h6><div class="bg-light p-2 rounded">{{ $announcement }}</div></div></div>
    @endif

    @if($e->status === 'open' && $u->hasRole('teller', 'accountant'))
        <div class="card mb-3"><div class="card-body"><h6>Record a contribution</h6>
            <form method="POST" action="{{ route('welfare.contribute', $e) }}">@csrf
                <select name="member_id" class="member-select" required></select>
                <div class="row g-1 mt-1">
                    <div class="col-6"><input type="number" step="0.01" name="amount" value="{{ $e->levy_amount > 0 ? $e->levy_amount : '' }}" class="form-control form-control-sm" placeholder="Amount" required></div>
                    <div class="col-6"><select name="channel" class="form-select form-select-sm"><option value="cash">Cash</option><option value="mpesa">M-Pesa</option><option value="bank">Bank</option><option value="savings">From member's savings</option></select></div>
                    <div class="col-12"><input name="reference" class="form-control form-control-sm" placeholder="Receipt / M-Pesa code"></div>
                </div>
                <button class="btn btn-sm btn-success w-100 mt-2">Record</button></form>
            <p class="small text-muted mt-2 mb-0">M-Pesa payments to account <code>member no.-W{{ $e->id }}</code> post automatically.</p>
        </div></div>
    @endif

    @if(in_array($e->status, ['open', 'closed']) && $u->hasRole('manager', 'accountant'))
        <div class="card mb-3"><div class="card-body"><h6>Pay the member</h6>
            <form method="POST" action="{{ route('welfare.payout', $e) }}" data-confirm="Pay this amount to {{ $e->member->full_name }}?">@csrf
                <label class="form-label small">Amount</label>
                <input type="number" step="0.01" name="amount" value="{{ $e->payable() }}" class="form-control mb-2" required>
                <select name="channel" class="form-select mb-2"><option value="savings">Credit member's savings (FOSA)</option><option value="mpesa">M-Pesa</option><option value="bank">Bank transfer</option><option value="cheque">Cheque</option><option value="cash">Cash</option></select>
                <input name="reference" class="form-control mb-2" placeholder="Transaction / cheque reference">
                <button class="btn btn-primary w-100">Pay out</button></form>
            <p class="small text-muted mt-2 mb-0">Raised {{ money($e->raised, 0) }} + fund benefit {{ money($e->fund_contribution, 0) }} − paid {{ money($e->paid_out, 0) }}. Welfare fund balance: KES {{ money($fund, 0) }}.</p>
        </div></div>
    @endif

    <div class="card"><div class="card-body small">
        <div>Created by {{ $e->creator?->name ?? 'member (portal)' }} · {{ $e->created_at->format('d/m/Y H:i') }}</div>
        @if($e->approver)<div>Opened by {{ $e->approver->name }} · {{ $e->approved_at?->format('d/m/Y H:i') }}</div>@endif
        @if($e->paid_on)<div>Last payout {{ $e->paid_on->format('d/m/Y') }} via {{ $e->payout_channel }} {{ $e->payout_ref }}</div>@endif
    </div></div>
</div>
</div>
@endsection
