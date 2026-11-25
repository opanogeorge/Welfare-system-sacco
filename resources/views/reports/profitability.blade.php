@extends('layouts.app')
@section('title', 'Member Profitability')
@section('content')
<div class="card"><div class="card-body">
    <form class="row g-2 mb-3 no-print">
        <div class="col-auto"><input type="date" name="from" value="{{ $from }}" class="form-control"></div>
        <div class="col-auto"><input type="date" name="to" value="{{ $to }}" class="form-control"></div>
        <div class="col-auto"><button class="btn btn-outline-secondary">Run</button></div>
    </form>
    <p class="small text-muted">Income earned from each member (loan interest, penalties, fees) less dividends and interest credited to them in the period.</p>
    <table class="table table-sm table-hover">
        <thead><tr><th>#</th><th>Member</th><th class="text-end">Income earned</th><th class="text-end">Dividends/interest paid</th><th class="text-end">Net contribution</th></tr></thead>
        @foreach($rows as $i => $r)
            <tr><td>{{ $i + 1 }}</td><td><a href="{{ route('members.show', $r['member']) }}">{{ $r['member']->member_no }}</a> {{ $r['member']->full_name }}</td>
                <td class="text-money">{{ money($r['income']) }}</td><td class="text-money">{{ money($r['paid']) }}</td>
                <td class="text-money fw-semibold {{ $r['net'] < 0 ? 'text-danger' : 'text-success' }}">{{ money($r['net']) }}</td></tr>
        @endforeach
        <tfoot class="fw-bold"><tr><td colspan="2">Totals</td><td class="text-money">{{ money($rows->sum('income')) }}</td><td class="text-money">{{ money($rows->sum('paid')) }}</td><td class="text-money">{{ money($rows->sum('net')) }}</td></tr></tfoot>
    </table>
</div></div>
@endsection
