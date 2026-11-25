@extends('layouts.app')
@section('title', 'Deposit / Withdraw')
@section('content')
<div class="row g-3">
<div class="col-lg-7">
<div class="card"><div class="card-body">
    <form method="POST" action="{{ route('transactions.store') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label">Member *</label>
            <select name="member_id" id="member_id" required>
                @if($member)<option value="{{ $member->id }}" selected>{{ $member->member_no }} - {{ $member->full_name }}</option>@endif
            </select>
        </div>
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Transaction *</label>
                <div class="btn-group w-100" role="group">
                    <input type="radio" class="btn-check" name="action" value="deposit" id="a1" @checked(old('action', 'deposit') === 'deposit')><label class="btn btn-outline-success" for="a1">Deposit</label>
                    <input type="radio" class="btn-check" name="action" value="withdrawal" id="a2" @checked(old('action') === 'withdrawal')><label class="btn btn-outline-danger" for="a2">Withdrawal</label>
                </div></div>
            <div class="col-md-6"><label class="form-label">Account *</label>
                <select name="account" class="form-select" required>
                    @foreach($types as $t)<option value="{{ $t->code }}" @selected(old('account') === $t->code)>{{ $t->name }} {{ $t->withdrawable ? '' : '(non-withdrawable)' }}</option>@endforeach
                </select></div>
            <div class="col-md-6"><label class="form-label">Amount (KES) *</label><input type="number" step="0.01" min="1" name="amount" value="{{ old('amount') }}" class="form-control form-control-lg" required></div>
            <div class="col-md-6"><label class="form-label">Date *</label><input type="date" name="txn_date" value="{{ old('txn_date', today()->toDateString()) }}" max="{{ today()->toDateString() }}" class="form-control form-control-lg" required></div>
            <div class="col-md-6"><label class="form-label">Channel *</label>
                <select name="channel" class="form-select">@foreach(['cash' => 'Cash', 'mpesa' => 'M-Pesa', 'bank' => 'Bank deposit / transfer', 'cheque' => 'Cheque', 'payroll' => 'Payroll'] as $k => $v)<option value="{{ $k }}" @selected(old('channel') === $k)>{{ $v }}</option>@endforeach</select></div>
            <div class="col-md-6"><label class="form-label">External ref (M-Pesa code / slip no.)</label><input name="external_ref" value="{{ old('external_ref') }}" class="form-control text-uppercase"></div>
            <div class="col-12"><label class="form-label">Narration</label><input name="description" value="{{ old('description') }}" class="form-control"></div>
        </div>
        <button class="btn btn-success btn-lg mt-3"><i class="bi bi-check2-circle"></i> Post transaction</button>
    </form>
</div></div>
</div>
<div class="col-lg-5">
    <div class="card"><div class="card-body" id="balances">
        <h6>Member balances</h6>
        @if($member)
            <table class="table table-sm">@foreach($member->accounts as $a)<tr><td>{{ $a->type->name }}</td><td class="text-money">{{ money($a->balance) }}</td></tr>@endforeach</table>
            <a href="{{ route('members.show', $member) }}" class="small">Open member profile</a>
        @else <p class="text-muted small">Select a member to see balances.</p> @endif
    </div></div>
</div>
</div>
@endsection
@push('scripts')
<script>
memberPicker(document.getElementById('member_id'), id => { if (id) location.href = '{{ route('transactions.create') }}?member=' + id; });
</script>
@endpush
