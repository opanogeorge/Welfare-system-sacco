@extends('layouts.app')
@section('title', "Journal {$entry->entry_no}")
@section('content')
@include('accounting._nav')
<div class="card"><div class="card-body">
    <div class="d-flex mb-3">
        <div><h5 class="mb-0">{{ $entry->description }}</h5>
            <small class="text-muted">{{ $entry->entry_date->format('d M Y') }} · {{ $entry->source_type ? class_basename($entry->source_type) . ' #' . $entry->source_id : 'Manual journal' }}
                · created by {{ $entry->creator?->name ?? 'System' }} @if($entry->poster) · posted by {{ $entry->poster->name }}@endif</small></div>
        <span class="badge text-bg-{{ ['posted' => 'success', 'draft' => 'warning', 'reversed' => 'secondary'][$entry->status] }} ms-auto align-self-start">{{ $entry->status }}</span>
    </div>
    <table class="table table-sm">
        <thead><tr><th>Account</th><th>Memo</th><th>Member</th><th class="text-end">Debit</th><th class="text-end">Credit</th></tr></thead>
        @foreach($entry->lines as $l)
            <tr><td><a href="{{ route('accounting.ledger', $l->account) }}">{{ $l->account->code }} {{ $l->account->name }}</a></td><td class="small">{{ $l->memo }}</td>
                <td class="small">{{ $l->member?->member_no }}</td>
                <td class="text-money">{{ (float) $l->debit ? money($l->debit) : '' }}</td><td class="text-money">{{ (float) $l->credit ? money($l->credit) : '' }}</td></tr>
        @endforeach
        <tfoot class="fw-bold"><tr><td colspan="3">Total</td><td class="text-money">{{ money($entry->lines->sum('debit')) }}</td><td class="text-money">{{ money($entry->lines->sum('credit')) }}</td></tr></tfoot>
    </table>
    <div class="d-flex gap-2">
        @if($entry->status === 'draft')
            <form method="POST" action="{{ route('accounting.journals.post', $entry) }}">@csrf<button class="btn btn-success">Approve &amp; post</button></form>
        @endif
        @if($entry->status === 'posted' && ! $entry->source_type)
            <form method="POST" action="{{ route('accounting.journals.reverse', $entry) }}" class="d-flex gap-1">@csrf
                <input name="reason" class="form-control form-control-sm" placeholder="Reason" required><button class="btn btn-sm btn-outline-danger">Reverse</button></form>
        @endif
    </div>
</div></div>
@endsection
