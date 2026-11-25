@extends('layouts.app')
@section('title', 'Bank Accounts')
@section('content')
@include('accounting._nav')
<div class="row g-3">
<div class="col-lg-8"><div class="card"><div class="card-body">
    <table class="table align-middle">
        <thead><tr><th>Account</th><th>Bank</th><th>Number</th><th>GL account</th><th class="text-end">Book balance</th><th></th></tr></thead>
        @foreach($banks as $b)
            <tr><td>{{ $b->name }}</td><td>{{ $b->bank }}</td><td>{{ $b->account_number }}</td><td class="small">{{ $b->glAccount->code }} {{ $b->glAccount->name }}</td>
                <td class="text-money">{{ money($b->glAccount->balance()) }}</td>
                <td><a href="{{ route('accounting.banks.reconcile', $b) }}" class="btn btn-sm btn-outline-success">Reconcile</a></td></tr>
        @endforeach
    </table>
</div></div></div>
<div class="col-lg-4"><div class="card"><div class="card-body">
    <h6>Add bank account</h6>
    <form method="POST" action="{{ route('accounting.banks') }}">@csrf
        <input name="name" class="form-control mb-2" placeholder="Display name e.g. Equity - Collections" required>
        <input name="bank" class="form-control mb-2" placeholder="Bank">
        <input name="account_number" class="form-control mb-2" placeholder="Account number">
        <select name="gl_account_id" class="form-select mb-2" required>@foreach($cashAccounts as $a)<option value="{{ $a->id }}">{{ $a->code }} {{ $a->name }}</option>@endforeach</select>
        <div class="form-text mb-2">Create a dedicated GL account (flagged “cash”) first for each extra bank.</div>
        <button class="btn btn-success w-100">Add</button>
    </form>
</div></div></div>
</div>
@endsection
