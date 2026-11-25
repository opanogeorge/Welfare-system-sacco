@extends('layouts.app')
@section('title', 'Guarantor Management')
@section('content')
@php($u = auth()->user())
<div class="row g-3 mb-3">
    @foreach([['Active guarantees', $stats['active'], 'on running loans'], ['Current exposure', money($stats['exposure'], 0), 'falls as loans are repaid'],
              ['Awaiting consent', $stats['pending'], 'requests not yet answered'], ['Release requests', $stats['releases'], 'need a decision'],
              ['Registered guarantors', $stats['registered'], 'members opted in'], ['Suspended / blocked', $stats['blocked'], 'cannot guarantee']] as [$l, $v, $s])
        <div class="col-6 col-lg-2"><div class="card stat h-100"><div class="card-body"><div class="label">{{ $l }}</div><div class="value">{{ $v }}</div><small class="text-muted">{{ $s }}</small></div></div></div>
    @endforeach
</div>

<ul class="nav nav-pills mb-3">
    @foreach(['guarantees' => 'Guaranteed loans', 'releases' => 'Release requests (' . $releaseRequests->count() . ')', 'consents' => 'Awaiting consent (' . $pendingConsents->count() . ')', 'register' => 'Guarantor register'] as $k => $v)
        <li class="nav-item"><a class="nav-link {{ $tab === $k ? 'active' : '' }}" href="{{ route('guarantors.index', ['tab' => $k]) }}">{{ $v }}</a></li>
    @endforeach
    <li class="nav-item ms-auto"><a class="nav-link" href="{{ route('reports.guarantors') }}"><i class="bi bi-file-earmark-bar-graph"></i> Liability report</a></li>
</ul>

@if($tab === 'guarantees')
<div class="card"><div class="card-body">
    <form class="row g-2 mb-3">
        <input type="hidden" name="tab" value="guarantees">
        <div class="col-md-4"><input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Guarantor, borrower or loan no."></div>
        <div class="col-md-2"><select name="status" class="form-select form-select-sm"><option value="">Active &amp; pending</option>@foreach(\App\Models\LoanGuarantor::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach</select></div>
        <div class="col-md-2"><select name="loan_status" class="form-select form-select-sm"><option value="">Any loan status</option>@foreach(['guarantors_pending', 'pending', 'appraised', 'approved', 'disbursed', 'closed', 'written_off'] as $s)<option value="{{ $s }}" @selected(request('loan_status') === $s)>{{ str_replace('_', ' ', $s) }}</option>@endforeach</select></div>
        <div class="col-md-2"><div class="form-check mt-1"><input type="checkbox" name="arrears" value="1" class="form-check-input" id="arr" @checked(request('arrears'))><label for="arr" class="form-check-label small">Loans in arrears only</label></div></div>
        <div class="col-md-2"><button class="btn btn-sm btn-outline-secondary w-100">Filter</button></div>
    </form>
    <div class="table-responsive">
    <table class="table table-sm align-middle">
        <thead><tr><th>Guarantor</th><th>Borrower / loan</th><th class="text-end">Guaranteed</th><th class="text-end">Exposure now</th><th>Reduced</th><th>Borrower status</th><th>Guarantee</th></tr></thead>
        @forelse($guarantees as $g)
            @php($days = $g->loan->status === 'disbursed' ? $g->loan->daysInArrears() : 0)
            <tr><td><a href="{{ route('guarantors.show', $g->member) }}">{{ $g->member->member_no }}</a> {{ $g->member->full_name }}</td>
                <td><a href="{{ route('loans.show', $g->loan) }}">{{ $g->loan->loan_no }}</a> · {{ $g->loan->member->full_name }}<br><small class="text-muted">{{ $g->loan->product->name }} · {{ str_replace('_', ' ', $g->loan->status) }}</small></td>
                <td class="text-money">{{ money($g->amount) }}</td>
                <td class="text-money fw-semibold">{{ money($g->exposure()) }}</td>
                <td style="min-width:110px">@if($g->status === 'accepted' && $g->loan->status === 'disbursed')<div class="progress" style="height:6px"><div class="progress-bar" style="width: {{ $g->reducedPercent() }}%"></div></div><small class="text-muted">{{ $g->reducedPercent() }}%</small>@endif</td>
                <td>@if($g->loan->status !== 'disbursed')<span class="text-muted small">—</span>@elseif($days === 0)<span class="badge text-bg-success">Up to date</span>@else<span class="badge text-bg-{{ $days > 30 ? 'danger' : 'warning' }}">{{ $days }} days late</span>@endif</td>
                <td><span class="badge text-bg-{{ $g->badge() }}">{{ $g->status_label }}</span>@if($g->release_requested_at)<br><span class="badge text-bg-warning">release requested</span>@endif</td></tr>
        @empty<tr><td colspan="7" class="text-muted">No guarantees match.</td></tr>@endforelse
    </table>
    </div>
    {{ $guarantees->links() }}
</div></div>
@endif

@if($tab === 'releases')
<div class="card"><div class="card-body">
    <p class="small text-muted">Approving releases the guarantor only if the loan stays covered (other guarantees + borrower's deposits ≥ principal balance). Otherwise replace them from the loan page first.</p>
    <table class="table table-sm align-middle">
        <thead><tr><th>Asked</th><th>Guarantor</th><th>Loan</th><th class="text-end">Exposure</th><th>Reason</th><th></th></tr></thead>
        @forelse($releaseRequests as $g)
            <tr><td class="small">{{ $g->release_requested_at->format('d/m/Y') }}</td>
                <td>{{ $g->member->member_no }} {{ $g->member->full_name }}</td>
                <td><a href="{{ route('loans.show', $g->loan) }}">{{ $g->loan->loan_no }}</a> · {{ $g->loan->member->full_name }}</td>
                <td class="text-money">{{ money($g->exposure()) }}</td>
                <td class="small">{{ $g->release_request_reason }}</td>
                <td class="text-nowrap">@if($u->hasRole('manager'))
                    <form method="POST" action="{{ route('guarantors.release-decision', $g) }}" class="d-flex gap-1">@csrf
                        <input name="note" class="form-control form-control-sm" placeholder="Note (optional)">
                        <button name="decision" value="approve" class="btn btn-sm btn-primary">Release</button>
                        <button name="decision" value="reject" class="btn btn-sm btn-outline-danger">Decline</button></form>
                    @endif
                    <a href="{{ route('loans.show', $g->loan) }}#guarantors" class="small">replace…</a></td></tr>
        @empty<tr><td colspan="6" class="text-muted">No release requests.</td></tr>@endforelse
    </table>
</div></div>
@endif

@if($tab === 'consents')
<div class="card"><div class="card-body">
    <div class="d-flex mb-2"><p class="small text-muted mb-0">Requests lapse automatically after {{ setting('guarantor_request_days', 7) }} days; the borrower is told to add someone else.</p>
        @if($u->hasRole('manager', 'loan_officer'))<form method="POST" action="{{ route('guarantors.expire') }}" class="ms-auto">@csrf<button class="btn btn-sm btn-outline-secondary">Process lapsed requests now</button></form>@endif</div>
    <table class="table table-sm align-middle">
        <thead><tr><th>Guarantor</th><th>Borrower / loan</th><th class="text-end">Amount</th><th>Asked</th><th>Lapses</th><th></th></tr></thead>
        @forelse($pendingConsents as $g)
            <tr><td>{{ $g->member->member_no }} {{ $g->member->full_name }}<br><small class="text-muted">{{ $g->member->phone }}</small></td>
                <td><a href="{{ route('loans.show', $g->loan) }}">{{ $g->loan->loan_no }}</a> · {{ $g->loan->member->full_name }}</td>
                <td class="text-money">{{ money($g->amount) }}</td><td class="small">{{ $g->created_at->format('d/m') }}</td>
                <td class="small {{ $g->expires_at && $g->expires_at->lt(now()->addDays(2)) ? 'text-danger' : '' }}">{{ $g->expires_at?->diffForHumans() ?? '—' }}</td>
                <td>@if($u->hasRole('loan_officer'))
                    <form method="POST" action="{{ route('loans.guarantor.respond', $g) }}" class="d-inline">@csrf<input type="hidden" name="accept" value="1"><button class="btn btn-sm btn-outline-success">Signed form</button></form>
                    <form method="POST" action="{{ route('loans.guarantor.respond', $g) }}" class="d-inline">@csrf<input type="hidden" name="accept" value="0"><button class="btn btn-sm btn-outline-danger">Declined</button></form>
                @endif</td></tr>
        @empty<tr><td colspan="6" class="text-muted">No requests waiting.</td></tr>@endforelse
    </table>
</div></div>
@endif

@if($tab === 'register')
<div class="card"><div class="card-body">
    <form class="row g-2 mb-3">
        <input type="hidden" name="tab" value="register">
        <div class="col-md-4"><input name="rq" value="{{ request('rq') }}" class="form-control form-control-sm" placeholder="Member no. or name"></div>
        <div class="col-md-3"><select name="gstatus" class="form-select form-select-sm"><option value="">Any guarantor status</option>@foreach(['eligible', 'suspended', 'blocked'] as $s)<option value="{{ $s }}" @selected(request('gstatus') === $s)>{{ ucfirst($s) }}</option>@endforeach</select></div>
        <div class="col-md-3"><div class="form-check mt-1"><input type="checkbox" name="registered" value="1" class="form-check-input" id="reg" @checked(request('registered'))><label for="reg" class="form-check-label small">Registered only</label></div></div>
        <div class="col-md-2"><button class="btn btn-sm btn-outline-secondary w-100">Filter</button></div>
    </form>
    <table class="table table-sm align-middle">
        <thead><tr><th>Member</th><th>Status</th><th>Registered</th><th class="text-end">Limit</th><th class="text-end">Exposure</th><th class="text-end">Free</th><th class="text-end">Loans</th><th></th></tr></thead>
        @foreach($register as $m)
            @php($limit = $m->guaranteeLimit())
            @php($committed = $m->committedGuarantees())
            <tr><td><a href="{{ route('guarantors.show', $m) }}">{{ $m->member_no }}</a> {{ $m->full_name }}</td>
                <td><span class="badge text-bg-{{ ['eligible' => 'success', 'suspended' => 'warning', 'blocked' => 'danger'][$m->guarantor_status] ?? 'secondary' }}">{{ $m->guarantor_status }}</span></td>
                <td class="small">{{ $m->guarantor_registered_at?->format('d/m/Y') ?? '—' }}</td>
                <td class="text-money">{{ money($limit, 0) }}@if($m->max_guarantee_amount !== null)<i class="bi bi-pin-angle small" title="Individual limit"></i>@endif</td>
                <td class="text-money">{{ money($committed, 0) }}</td><td class="text-money">{{ money(max(0, $limit - $committed), 0) }}</td>
                <td class="text-end">{{ $m->active_guarantees }}</td>
                <td><a href="{{ route('guarantors.show', $m) }}" class="btn btn-sm btn-outline-secondary py-0">Open</a></td></tr>
        @endforeach
    </table>
    {{ $register->links() }}
</div></div>
@endif
@endsection
