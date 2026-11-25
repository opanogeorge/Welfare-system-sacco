@extends('layouts.app')
@section('title', 'Trial Balance')
@section('content')
@include('accounting._nav')
<div class="card col-xl-9"><div class="card-body">
    @include('accounting._period', ['heading' => 'TRIAL BALANCE'])
    <table class="table table-sm">
        <thead class="table-light"><tr><th>Code</th><th>Account</th><th class="text-end">Debit</th><th class="text-end">Credit</th></tr></thead>
        @foreach($tb['rows'] as $r)
            <tr><td>{{ $r['account']->code }}</td><td><a href="{{ route('accounting.ledger', $r['account']) }}">{{ $r['account']->name }}</a></td>
                <td class="text-money">{{ $r['debit'] ? money($r['debit']) : '' }}</td><td class="text-money">{{ $r['credit'] ? money($r['credit']) : '' }}</td></tr>
        @endforeach
        <tfoot class="fw-bold border-top"><tr><td colspan="2">TOTAL</td><td class="text-money">{{ money($tb['debit']) }}</td><td class="text-money">{{ money($tb['credit']) }}</td></tr></tfoot>
    </table>
    @if(abs($tb['debit'] - $tb['credit']) > 0.01)<div class="alert alert-danger">Trial balance is out by {{ money($tb['debit'] - $tb['credit']) }}.</div>
    @else<div class="text-success small"><i class="bi bi-check-circle"></i> Balanced</div>@endif
</div></div>
@endsection
