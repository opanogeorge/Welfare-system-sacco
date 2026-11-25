@extends('layouts.app')
@section('title', 'Cash Book Summary')
@section('content')
<div class="card"><div class="card-body">
    <form class="row g-2 mb-3 no-print">
        <div class="col-auto"><input type="date" name="from" value="{{ $from }}" class="form-control"></div>
        <div class="col-auto"><input type="date" name="to" value="{{ $to }}" class="form-control"></div>
        <div class="col-auto"><button class="btn btn-outline-secondary">Filter</button></div>
        <div class="col-auto ms-auto"><a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn btn-outline-secondary"><i class="bi bi-download"></i> CSV</a></div>
    </form>
    @php($in = $rows->where('direction', 'credit')->sum('total')) @php($out = $rows->where('direction', 'debit')->sum('total'))
    <table class="table table-sm">
        <thead><tr><th>Type</th><th>Channel</th><th class="text-end">Count</th><th class="text-end">Money in</th><th class="text-end">Money out</th></tr></thead>
        @foreach($rows as $r)
            <tr><td>{{ str_replace('_', ' ', $r->type) }}</td><td>{{ $r->channel }}</td><td class="text-money">{{ $r->n }}</td>
                <td class="text-money text-success">{{ $r->direction === 'credit' ? money($r->total) : '' }}</td><td class="text-money text-danger">{{ $r->direction === 'debit' ? money($r->total) : '' }}</td></tr>
        @endforeach
        <tr><td colspan="4">Operating expenses</td><td class="text-money text-danger">{{ money($expenses) }}</td></tr>
        <tfoot class="fw-bold"><tr><td colspan="3">Totals</td><td class="text-money">{{ money($in) }}</td><td class="text-money">{{ money($out + $expenses) }}</td></tr>
            <tr><td colspan="3">Net cash movement</td><td colspan="2" class="text-money">{{ money($in - $out - $expenses) }}</td></tr></tfoot>
    </table>
</div></div>
@endsection
