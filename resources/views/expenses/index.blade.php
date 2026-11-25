@extends('layouts.app')
@section('title', 'Expenses & Payables')
@section('content')
<div class="row g-3">
<div class="col-lg-8"><div class="card"><div class="card-body">
    <form class="mb-3 d-flex gap-2">
        <select name="account" class="form-select w-auto"><option value="">All expense accounts</option>@foreach($expenseAccounts as $a)<option value="{{ $a->id }}" @selected(request('account') == $a->id)>{{ $a->name }}</option>@endforeach</select>
        <label class="small align-self-center"><input type="checkbox" name="unpaid" value="1" @checked(request('unpaid'))> Unpaid bills only</label>
        <button class="btn btn-outline-secondary">Filter</button>
        <span class="ms-auto small align-self-center">Accounts payable: <strong class="text-danger">KES {{ money($payables) }}</strong></span>
    </form>
    <table class="table table-sm table-hover align-middle">
        <thead><tr><th>Date</th><th>Account</th><th>Description</th><th>Payee</th><th class="text-end">Amount</th><th>Paid from</th><th></th></tr></thead>
        @forelse($expenses as $e)
            <tr><td>{{ $e->spent_on->format('d/m/Y') }}</td><td class="small">{{ $e->glAccount?->name ?? $e->category }}</td><td>{{ $e->description }} <span class="small text-muted">{{ $e->reference }}</span></td>
                <td>{{ $e->payee }}</td><td class="text-money">{{ money($e->amount) }}</td>
                <td class="small">{{ $e->is_paid ? $e->paidFrom?->name : '' }}</td>
                <td>@unless($e->is_paid)
                    <form method="POST" action="{{ route('expenses.pay', $e) }}" class="d-flex gap-1">@csrf
                        <select name="paid_from_gl_id" class="form-select form-select-sm">@foreach($cashAccounts as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>
                        <input type="hidden" name="paid_on" value="{{ today()->toDateString() }}"><button class="btn btn-sm btn-outline-success">Pay</button></form>
                @endunless</td></tr>
        @empty <tr><td colspan="7" class="text-muted">No expenses recorded.</td></tr> @endforelse
    </table>
    {{ $expenses->links() }}
</div></div></div>
<div class="col-lg-4"><div class="card"><div class="card-body">
    <h6>Record expense / bill</h6>
    <form method="POST" action="{{ route('expenses.store') }}">@csrf
        <select name="gl_account_id" class="form-select mb-2" required>@foreach($expenseAccounts as $a)<option value="{{ $a->id }}">{{ $a->code }} {{ $a->name }}</option>@endforeach</select>
        <input name="description" class="form-control mb-2" placeholder="Description" required>
        <input type="number" step="0.01" name="amount" class="form-control mb-2" placeholder="Amount (KES)" required>
        <input type="date" name="spent_on" value="{{ today()->toDateString() }}" class="form-control mb-2" required>
        <input name="payee" class="form-control mb-2" placeholder="Payee / supplier">
        <input name="reference" class="form-control mb-2" placeholder="Voucher / invoice no.">
        <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_paid" value="1" id="ip" checked onchange="pf.disabled = !this.checked"><label class="form-check-label" for="ip">Paid now</label></div>
        <select name="paid_from_gl_id" id="pf" class="form-select mb-2">@foreach($cashAccounts as $c)<option value="{{ $c->id }}">From {{ $c->name }}</option>@endforeach</select>
        <button class="btn btn-success w-100">Save</button>
    </form>
    <p class="small text-muted mt-2 mb-0">Posts to the general ledger immediately. Unticked “Paid now” records the bill in Accounts Payable.</p>
</div></div></div>
</div>
@endsection
