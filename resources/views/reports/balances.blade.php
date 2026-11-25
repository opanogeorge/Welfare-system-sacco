@extends('layouts.app')
@section('title', 'Member Balances')
@section('content')
<div class="card"><div class="card-body">
    <div class="d-flex mb-3 no-print"><a href="?export=csv" class="btn btn-outline-secondary ms-auto"><i class="bi bi-download"></i> Full CSV</a></div>
    <div class="table-responsive">
    <table class="table table-sm table-hover">
        <thead><tr><th>Member No</th><th>Name</th>@foreach($types as $t)<th class="text-end">{{ $t->code }}</th>@endforeach<th class="text-end">Loans</th></tr></thead>
        @foreach($members as $m)
            <tr><td><a href="{{ route('members.show', $m) }}">{{ $m->member_no }}</a></td><td>{{ $m->full_name }}</td>
                @foreach($types as $t)<td class="text-money">{{ money(optional($m->accounts->firstWhere('account_type_id', $t->id))->balance) }}</td>@endforeach
                <td class="text-money">{{ money($m->outstandingLoans()) }}</td></tr>
        @endforeach
        <tfoot class="fw-bold"><tr><td colspan="2">SACCO totals (all members)</td>@foreach($types as $t)<td class="text-money">{{ money($totals[$t->id]) }}</td>@endforeach<td></td></tr></tfoot>
    </table>
    </div>
    {{ $members->links() }}
</div></div>
@endsection
