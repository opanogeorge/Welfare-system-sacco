@extends('layouts.app')
@section('title', $member->member_no . ' · ' . $member->full_name)
@section('content')
<div class="d-flex flex-wrap gap-2 mb-3">
    <a href="{{ route('members.edit', $member) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i> Edit</a>
    <a href="{{ route('members.statement', $member) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark-text"></i> Statement</a>
    @if(auth()->user()->hasRole('teller', 'accountant'))
        <a href="{{ route('transactions.create', ['member' => $member->id]) }}" class="btn btn-sm btn-success"><i class="bi bi-plus-circle"></i> Deposit / Withdraw</a>
    @endif
    @if(auth()->user()->hasRole('loan_officer'))
        <a href="{{ route('loans.create', ['member' => $member->id]) }}" class="btn btn-sm btn-primary"><i class="bi bi-cash-coin"></i> New loan</a>
    @endif
    <a href="{{ route('members.statement.pdf', $member) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-pdf"></i> PDF</a>
    <a href="{{ route('guarantors.show', $member) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-people"></i> As guarantor @if($member->guarantor_status !== 'eligible')<span class="badge text-bg-danger">{{ $member->guarantor_status }}</span>@endif</a>
    @if($member->status === 'pending' && auth()->user()->hasRole('manager'))
        <form method="POST" action="{{ route('members.approve', $member) }}" class="d-inline">@csrf<button class="btn btn-sm btn-warning"><i class="bi bi-check2-circle"></i> Approve membership</button></form>
    @endif
    @if(auth()->user()->hasRole('manager', 'accountant') && $member->status !== 'exited')
        <a href="{{ route('members.exit', $member) }}" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-right"></i> Exit / clearance</a>
    @endif
    @if(auth()->user()->hasRole('manager'))
    <form method="POST" action="{{ route('members.status', $member) }}" class="ms-auto d-flex gap-1" data-confirm="Change member status?">
        @csrf
        <select name="status" class="form-select form-select-sm w-auto">
            @foreach(['active', 'dormant', 'suspended', 'exited'] as $s)<option @selected($member->status === $s)>{{ $s }}</option>@endforeach
        </select>
        <button class="btn btn-sm btn-outline-danger">Set status</button>
    </form>
    @endif
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card mb-3"><div class="card-body">
            <div class="text-center mb-3">
                <div class="rounded-circle bg-success text-white d-inline-flex align-items-center justify-content-center" style="width:64px;height:64px;font-size:1.5rem">{{ substr($member->first_name, 0, 1) }}{{ substr($member->last_name, 0, 1) }}</div>
                <h5 class="mt-2 mb-0">{{ $member->full_name }}</h5>
                <span class="badge text-bg-{{ $member->status === 'active' ? 'success' : 'secondary' }}">{{ $member->status }}</span>
            </div>
            <dl class="row small mb-0">
                <dt class="col-5">Member No</dt><dd class="col-7">{{ $member->member_no }}</dd>
                <dt class="col-5">National ID</dt><dd class="col-7">{{ $member->national_id }}</dd>
                <dt class="col-5">KRA PIN</dt><dd class="col-7">{{ $member->kra_pin ?: '—' }}</dd>
                <dt class="col-5">Phone</dt><dd class="col-7">{{ $member->phone }}</dd>
                <dt class="col-5">Email</dt><dd class="col-7">{{ $member->email ?: '—' }}</dd>
                <dt class="col-5">Employer</dt><dd class="col-7">{{ $member->employer ?: '—' }} {{ $member->payroll_no ? "({$member->payroll_no})" : '' }}</dd>
                <dt class="col-5">Monthly contrib.</dt><dd class="col-7">KES {{ money($member->monthly_contribution) }}</dd>
                <dt class="col-5">Joined</dt><dd class="col-7">{{ $member->joined_on->format('d M Y') }} ({{ $member->monthsOfMembership() }} mo)</dd>
                <dt class="col-5">Portal login</dt><dd class="col-7">{{ $member->user ? 'Yes' : 'No' }}</dd>
                <dt class="col-5">Category</dt><dd class="col-7">{{ $member->category?->name ?? '—' }}</dd>
                <dt class="col-5">Risk / PEP</dt><dd class="col-7">{{ ucfirst($member->risk_rating) }} {{ $member->is_pep ? '· PEP' : '' }}</dd>
                <dt class="col-5">Data consent</dt><dd class="col-7">{{ $member->data_consent_at?->format('d M Y') ?? 'Not recorded' }}</dd>
                @if($member->approved_at)<dt class="col-5">Approved</dt><dd class="col-7">{{ $member->approved_at->format('d M Y') }}</dd>@endif
            </dl>
        </div></div>

        <div class="card mb-3"><div class="card-body">
            <div class="d-flex align-items-center mb-2"><h6 class="mb-0">Identity</h6>
                <span class="badge ms-2 text-bg-{{ ['verified' => 'success', 'failed' => 'danger'][$member->identity_status] ?? 'secondary' }}">{{ $member->identity_status }}</span>
                <a href="{{ route('members.identity', $member) }}" class="ms-auto small">Verify identity</a></div>
            <div class="small text-muted mb-2">Joined via {{ $member->onboarding_channel }} · CRB consent {{ $member->crb_consent ? 'yes' : 'no' }}
                @if($member->branch) · Branch {{ $member->branch->name }}@endif</div>
            @if(auth()->user()->hasRole('manager') && auth()->user()->isHq() && \App\Models\Branch::count())
                <form method="POST" action="{{ route('members.branch', $member) }}" class="d-flex gap-1 mb-3">@csrf
                    <select name="branch_id" class="form-select form-select-sm">@foreach(\App\Models\Branch::where('is_active', true)->orderBy('name')->get() as $b)<option value="{{ $b->id }}" @selected($member->branch_id === $b->id)>{{ $b->name }}</option>@endforeach</select>
                    <button class="btn btn-sm btn-outline-secondary text-nowrap">Move branch</button></form>
            @endif
            <h6>Documents (KYC)</h6>
            <table class="table table-sm small">
                @forelse($member->documents as $d)
                    <tr><td><a href="{{ route('documents.show', $d) }}" target="_blank">{{ $d->title }}</a></td>
                        <td>@if($d->verified)<i class="bi bi-patch-check-fill text-success" title="Verified"></i>@elseif(auth()->user()->hasRole('manager', 'loan_officer'))
                            <form method="POST" action="{{ route('documents.verify', $d) }}">@csrf<button class="btn btn-sm btn-link p-0">verify</button></form>@endif</td>
                        <td>@if(auth()->user()->hasRole('manager'))<form method="POST" action="{{ route('documents.destroy', $d) }}" data-confirm="Delete document?">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-trash"></i></button></form>@endif</td></tr>
                @empty <tr><td class="text-muted">No documents uploaded.</td></tr> @endforelse
            </table>
            <form method="POST" action="{{ route('members.documents.store', $member) }}" enctype="multipart/form-data" class="row g-1">@csrf
                <div class="col-6"><select name="type" class="form-select form-select-sm">@foreach(['national_id' => 'National ID', 'passport' => 'Passport', 'photo' => 'Photo', 'kra_pin' => 'KRA PIN cert', 'payslip' => 'Payslip', 'signature' => 'Signature', 'application_form' => 'Application form', 'other' => 'Other'] as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                <div class="col-6"><input name="title" class="form-control form-control-sm" placeholder="Title (optional)"></div>
                <div class="col-9"><input type="file" name="file" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png" required></div>
                <div class="col-3"><button class="btn btn-sm btn-outline-success w-100">Upload</button></div>
            </form>
        </div></div>

        @if($member->fixedDeposits->count())
        <div class="card mb-3"><div class="card-body"><h6>Fixed deposits</h6>
            <table class="table table-sm small mb-0">@foreach($member->fixedDeposits as $f)<tr><td>{{ $f->fd_no }}</td><td class="text-money">{{ money($f->amount) }}</td><td>{{ $f->rate }}%</td><td>{{ $f->maturity_date->format('d/m/Y') }}</td></tr>@endforeach</table>
        </div></div>
        @endif

        <div class="card"><div class="card-body">
            <h6>Nominees / next of kin</h6>
            <table class="table table-sm small">
                @forelse($member->nominees as $n)
                    <tr><td>{{ $n->name }}<br><span class="text-muted">{{ $n->relationship }} · {{ $n->phone }}</span></td><td class="text-end">{{ $n->percentage }}%</td>
                        <td><form method="POST" action="{{ route('members.nominees.destroy', $n) }}" data-confirm="Remove nominee?">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-x"></i></button></form></td></tr>
                @empty <tr><td class="text-muted">None recorded.</td></tr> @endforelse
            </table>
            <form method="POST" action="{{ route('members.nominees.store', $member) }}" class="row g-1">
                @csrf
                <div class="col-7"><input name="name" class="form-control form-control-sm" placeholder="Name" required></div>
                <div class="col-5"><input name="relationship" class="form-control form-control-sm" placeholder="Relationship" required></div>
                <div class="col-5"><input name="phone" class="form-control form-control-sm" placeholder="Phone"></div>
                <div class="col-4"><input name="national_id" class="form-control form-control-sm" placeholder="ID No"></div>
                <div class="col-3"><input name="percentage" type="number" class="form-control form-control-sm" placeholder="%" required></div>
                <div class="col-12"><button class="btn btn-sm btn-outline-success w-100">Add nominee</button></div>
            </form>
        </div></div>
    </div>

    <div class="col-lg-8">
        <div class="row g-3 mb-3">
            @foreach($member->accounts as $a)
                <div class="col-md-4"><div class="card stat h-100"><div class="card-body">
                    <div class="label">{{ $a->type->name }}</div>
                    <div class="value">{{ money($a->balance) }}</div>
                    <small class="text-muted">{{ $a->account_no }}</small>
                </div></div></div>
            @endforeach
            <div class="col-md-4"><div class="card stat h-100"><div class="card-body">
                <div class="label">Loan balance</div><div class="value text-danger">{{ money($member->outstandingLoans()) }}</div>
                <small class="text-muted">Free to guarantee: {{ money($member->freeGuaranteeCapacity()) }}</small>
            </div></div></div>
        </div>

        <div class="card mb-3"><div class="card-body">
            <h6>Loans</h6>
            <table class="table table-sm table-hover">
                <thead><tr><th>Loan No</th><th>Product</th><th class="text-end">Amount</th><th class="text-end">Balance</th><th>Status</th></tr></thead>
                @forelse($member->loans as $l)
                    <tr><td><a href="{{ route('loans.show', $l) }}">{{ $l->loan_no }}</a></td><td>{{ $l->product->name }}</td>
                        <td class="text-money">{{ money($l->amount_approved ?? $l->amount_applied) }}</td><td class="text-money">{{ money($l->outstanding) }}</td>
                        <td><span class="badge text-bg-{{ $l->badge() }}">{{ $l->status_label }}</span></td></tr>
                @empty <tr><td colspan="5" class="text-muted">No loans.</td></tr> @endforelse
            </table>
        </div></div>

        <div class="card mb-3"><div class="card-body">
            <h6>Loans guaranteed by this member</h6>
            <table class="table table-sm">
                @forelse($member->guarantees as $g)
                    <tr><td><a href="{{ route('loans.show', $g->loan) }}">{{ $g->loan->loan_no }}</a></td><td>{{ $g->loan->member->full_name }}</td>
                        <td class="text-money">{{ money($g->amount) }}</td><td><span class="badge text-bg-light">{{ $g->status }}</span></td></tr>
                @empty <tr><td class="text-muted">None.</td></tr> @endforelse
            </table>
        </div></div>

        <div class="card"><div class="card-body">
            <div class="d-flex"><h6>Recent transactions</h6><a href="{{ route('members.statement', $member) }}" class="ms-auto small">Full statement</a></div>
            <table class="table table-sm">
                <thead><tr><th>Date</th><th>Ref</th><th>Account</th><th>Type</th><th class="text-end">Amount</th><th class="text-end">Balance</th></tr></thead>
                @foreach($transactions as $t)
                    <tr class="{{ $t->reversed ? 'text-decoration-line-through text-muted' : '' }}">
                        <td>{{ $t->txn_date->format('d/m/Y') }}</td><td class="small">{{ $t->reference }}</td>
                        <td>{{ $t->account?->type->code ?? 'LOAN' }}</td><td>{{ str_replace('_', ' ', $t->type) }}</td>
                        <td class="text-money {{ $t->direction === 'credit' ? 'text-success' : 'text-danger' }}">{{ $t->direction === 'debit' ? '-' : '' }}{{ money($t->amount) }}</td>
                        <td class="text-money">{{ $t->balance_after !== null ? money($t->balance_after) : '' }}</td></tr>
                @endforeach
            </table>
        </div></div>
    </div>
</div>
@endsection
