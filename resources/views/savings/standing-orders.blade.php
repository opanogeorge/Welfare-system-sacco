@extends('layouts.app')
@section('title', 'Standing Orders')
@section('content')
<div class="row g-3">
<div class="col-lg-8"><div class="card"><div class="card-body">
    <table class="table table-sm align-middle">
        <thead><tr><th>Member</th><th>From</th><th>To</th><th class="text-end">Amount</th><th>Day</th><th>Last run</th><th>Status</th><th></th></tr></thead>
        @forelse($orders as $o)
            <tr><td>{{ $o->member->member_no }} {{ $o->member->full_name }}</td><td>{{ $o->fromAccount->type->code }}</td>
                <td>{{ $o->target_type === 'loan' ? $o->loan?->loan_no : $o->targetAccount?->type->code }}</td>
                <td class="text-money">{{ money($o->amount) }}</td><td>{{ $o->day_of_month }}</td>
                <td class="small">{{ $o->last_run_on?->format('d/m/Y') }} <span class="text-muted">{{ $o->last_result }}</span></td>
                <td><span class="badge text-bg-{{ $o->is_active ? 'success' : 'secondary' }}">{{ $o->is_active ? 'Active' : 'Stopped' }}</span></td>
                <td><form method="POST" action="{{ route('savings.so.toggle', $o) }}">@csrf<button class="btn btn-sm btn-link">{{ $o->is_active ? 'Stop' : 'Activate' }}</button></form></td></tr>
        @empty <tr><td colspan="8" class="text-muted">No standing orders.</td></tr> @endforelse
    </table>
    {{ $orders->links() }}
</div></div></div>
<div class="col-lg-4">
    <div class="card mb-3"><div class="card-body">
        <h6>New standing order</h6>
        <form method="POST">@csrf
            <select name="member_id" class="member-select" required></select>
            <input name="target" class="form-control my-2 text-uppercase" placeholder="Target: DEP, SHR or loan no. LN2026…" required>
            <div class="row g-2 mb-2"><div class="col"><input type="number" step="0.01" name="amount" class="form-control" placeholder="Amount" required></div>
                <div class="col"><input type="number" name="day_of_month" min="1" max="28" class="form-control" placeholder="Day (1-28)" required></div></div>
            <button class="btn btn-success w-100">Create</button>
        </form>
        <p class="small text-muted mt-2 mb-0">Runs automatically from the member's savings (FOSA) account on that day each month. Members get an SMS if funds are short.</p>
    </div></div>
    @if(auth()->user()->hasRole('accountant'))
    <div class="card"><div class="card-body">
        <h6>Post savings interest</h6>
        <form method="POST" action="{{ route('savings.interest') }}" class="d-flex gap-1">@csrf
            <input type="month" name="month" value="{{ now()->subMonthNoOverflow()->format('Y-m') }}" class="form-control"><button class="btn btn-outline-success">Run</button></form>
        <p class="small text-muted mt-2 mb-0">Uses each account type's interest rate (Settings). Runs automatically on the 1st; safe to re-run.</p>
    </div></div>
    @endif
</div>
</div>
@endsection
