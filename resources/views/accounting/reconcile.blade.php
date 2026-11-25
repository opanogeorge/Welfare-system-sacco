@extends('layouts.app')
@section('title', "Reconcile {$bank->name}")
@section('content')
@include('accounting._nav')
@if(! $rec)
    <div class="card col-md-6"><div class="card-body">
        <h6>Start a reconciliation</h6>
        <form method="POST" action="{{ route('accounting.banks.reconcile', $bank) }}">@csrf
            <label class="form-label small">Bank statement date</label><input type="date" name="statement_date" value="{{ today()->toDateString() }}" class="form-control mb-2" required>
            <label class="form-label small">Closing balance per bank statement</label><input type="number" step="0.01" name="statement_balance" class="form-control mb-3" required>
            <button class="btn btn-success">Start</button>
        </form>
    </div></div>
@else
<form method="POST" action="{{ route('accounting.reconciliations.save', $rec) }}">@csrf
<div class="row g-3 mb-3">
    @foreach(['Statement balance' => $rec->statement_balance, 'Cleared in books' => $rec->cleared_balance, 'Difference' => $rec->difference] as $k => $v)
        <div class="col-md-4"><div class="card stat"><div class="card-body"><div class="label">{{ $k }}</div><div class="value {{ $k === 'Difference' && abs($v) > 0.009 ? 'text-danger' : '' }}">{{ money($v) }}</div></div></div></div>
    @endforeach
</div>
<div class="card"><div class="card-body">
    <p class="small text-muted">Tick every book entry that appears on the bank statement up to {{ $rec->statement_date->format('d M Y') }}. Previously cleared: KES {{ money($clearedBefore) }}.</p>
    <table class="table table-sm">
        <thead><tr><th><input type="checkbox" onclick="document.querySelectorAll('.clr').forEach(c => c.checked = this.checked)"></th><th>Date</th><th>Entry</th><th>Description</th><th class="text-end">Receipts</th><th class="text-end">Payments</th></tr></thead>
        @forelse($lines as $l)
            <tr><td><input type="checkbox" class="clr" name="cleared[]" value="{{ $l->id }}" @checked($l->reconciliation_id === $rec->id)></td>
                <td>{{ $l->entry->entry_date->format('d/m/Y') }}</td><td>{{ $l->entry->entry_no }}</td><td class="small">{{ $l->entry->description }}</td>
                <td class="text-money">{{ (float) $l->debit ? money($l->debit) : '' }}</td><td class="text-money">{{ (float) $l->credit ? money($l->credit) : '' }}</td></tr>
        @empty <tr><td colspan="6" class="text-muted">No uncleared entries.</td></tr> @endforelse
    </table>
    <button class="btn btn-outline-success">Save &amp; recalculate</button>
    <button name="complete" value="1" class="btn btn-success">Complete reconciliation</button>
</div></div>
</form>
@endif
@if($history->count())
<div class="card mt-3"><div class="card-body"><h6>History</h6>
    <table class="table table-sm mb-0">@foreach($history as $h)<tr><td>{{ $h->statement_date->format('d/m/Y') }}</td><td class="text-money">{{ money($h->statement_balance) }}</td><td class="text-money">{{ money($h->difference) }}</td><td>{{ $h->status }}</td><td class="small">{{ $h->user?->name }}</td></tr>@endforeach</table>
</div></div>
@endif
@endsection
