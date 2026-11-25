@extends('layouts.app')
@section('title', 'Request a Withdrawal')
@section('content')
<div class="row g-3">
<div class="col-lg-5"><div class="card"><div class="card-body">
    <form method="POST">@csrf
        <label class="form-label">From account</label>
        <select name="account_code" class="form-select mb-2">
            @foreach($member->accounts->filter(fn ($a) => $a->type->withdrawable) as $a)
                <option value="{{ $a->type->code }}">{{ $a->type->name }} · available KES {{ money($a->availableBalance()) }}</option>
            @endforeach
        </select>
        <label class="form-label">Amount (KES)</label><input type="number" step="0.01" name="amount" class="form-control mb-2" required>
        <label class="form-label">Pay to</label>
        <div class="row g-2 mb-2"><div class="col-5"><select name="channel" class="form-select"><option value="mpesa">M-Pesa</option><option value="bank">Bank</option><option value="cash">Collect cash</option></select></div>
            <div class="col-7"><input name="destination" class="form-control" placeholder="{{ $member->phone }} / bank a/c"></div></div>
        <input name="reason" class="form-control mb-3" placeholder="Reason (optional)">
        <button class="btn btn-success w-100">Submit request</button>
    </form>
    <p class="small text-muted mt-2 mb-0">Deposits (BOSA) and share capital can't be withdrawn while you're a member. Withdrawal fees apply as per SACCO policy.</p>
</div></div></div>
<div class="col-lg-7"><div class="card"><div class="card-body">
    <h6>My requests</h6>
    <table class="table table-sm">@forelse($requests as $r)<tr><td>{{ $r->created_at->format('d/m/Y') }}</td><td>{{ $r->account_code }}</td><td class="text-money">{{ money($r->amount) }}</td><td>{{ $r->channel }}</td>
        <td><span class="badge text-bg-{{ ['paid' => 'success', 'rejected' => 'danger', 'pending' => 'warning'][$r->status] }}">{{ $r->status }}</span> <small class="text-muted">{{ $r->remarks }}</small></td></tr>
        @empty<tr><td class="text-muted">No requests yet.</td></tr>@endforelse</table>
</div></div></div>
</div>
@endsection
