@extends('layouts.app')
@section('title', 'Loan Portfolio')
@section('content')
<div class="card"><div class="card-body">
    <div class="d-flex mb-3 no-print"><a href="?export=csv" class="btn btn-outline-secondary ms-auto"><i class="bi bi-download"></i> CSV</a><button onclick="print()" class="btn btn-outline-secondary ms-2"><i class="bi bi-printer"></i></button></div>
    <table class="table">
        <thead><tr><th>Product</th><th class="text-end">Loans</th><th class="text-end">Amount disbursed</th><th class="text-end">Principal bal.</th><th class="text-end">Interest bal.</th><th class="text-end">Penalty bal.</th><th class="text-end">Total outstanding</th></tr></thead>
        @foreach($rows as $r)
            <tr><td>{{ $r['product'] }}</td><td class="text-money">{{ $r['count'] }}</td><td class="text-money">{{ money($r['disbursed']) }}</td><td class="text-money">{{ money($r['principal']) }}</td>
                <td class="text-money">{{ money($r['interest']) }}</td><td class="text-money">{{ money($r['penalty']) }}</td><td class="text-money fw-semibold">{{ money($r['principal'] + $r['interest'] + $r['penalty']) }}</td></tr>
        @endforeach
        <tfoot class="fw-bold"><tr><td>Total</td><td class="text-money">{{ $rows->sum('count') }}</td><td class="text-money">{{ money($rows->sum('disbursed')) }}</td><td class="text-money">{{ money($rows->sum('principal')) }}</td>
            <td class="text-money">{{ money($rows->sum('interest')) }}</td><td class="text-money">{{ money($rows->sum('penalty')) }}</td><td class="text-money">{{ money($rows->sum('principal') + $rows->sum('interest') + $rows->sum('penalty')) }}</td></tr></tfoot>
    </table>
</div></div>
@endsection
