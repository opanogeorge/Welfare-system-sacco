@extends('layouts.app')
@section('title', "Ledger: {$account->code} {$account->name}")
@section('content')
@include('accounting._nav')
<div class="card"><div class="card-body">
    <form class="row g-2 mb-3 no-print">
        <div class="col-auto"><input type="date" name="from" value="{{ $from }}" class="form-control"></div>
        <div class="col-auto"><input type="date" name="to" value="{{ $to }}" class="form-control"></div>
        <div class="col-auto"><button class="btn btn-outline-secondary">Filter</button> <button type="button" onclick="print()" class="btn btn-outline-secondary"><i class="bi bi-printer"></i></button></div>
    </form>
    @php($bal = $opening) @php($sign = $account->isDebitNormal() ? 1 : -1)
    <table class="table table-sm">
        <thead><tr><th>Date</th><th>Entry</th><th>Description</th><th>Member</th><th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end">Balance</th></tr></thead>
        <tr class="table-light"><td colspan="6">Opening balance</td><td class="text-money">{{ money($opening) }}</td></tr>
        @foreach($lines as $l)
            @php($bal += $sign * ((float) $l->debit - (float) $l->credit))
            <tr><td>{{ $l->entry->entry_date->format('d/m/Y') }}</td><td><a href="{{ route('accounting.journals.show', $l->entry) }}">{{ $l->entry->entry_no }}</a></td>
                <td class="small">{{ $l->entry->description }} @if($l->memo)<span class="text-muted">· {{ $l->memo }}</span>@endif</td>
                <td class="small">{{ $l->member?->member_no }}</td>
                <td class="text-money">{{ (float) $l->debit ? money($l->debit) : '' }}</td><td class="text-money">{{ (float) $l->credit ? money($l->credit) : '' }}</td>
                <td class="text-money">{{ money($bal) }}</td></tr>
        @endforeach
        <tfoot class="fw-bold"><tr><td colspan="4">Totals / closing</td><td class="text-money">{{ money($lines->sum('debit')) }}</td><td class="text-money">{{ money($lines->sum('credit')) }}</td><td class="text-money">{{ money($bal) }}</td></tr></tfoot>
    </table>
</div></div>
@endsection
