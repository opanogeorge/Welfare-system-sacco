@extends('layouts.app')
@section('title', "Check-off {$schedule->employer->name} · {$schedule->period}")
@section('content')
<div class="row g-3 mb-3">
    @foreach(['Expected' => $schedule->expected_total, 'Deducted & posted' => $schedule->received_total, 'Remitted' => $schedule->remittances->sum('amount'), 'Variance' => $schedule->received_total - $schedule->expected_total] as $k => $v)
        <div class="col-md-3"><div class="card stat"><div class="card-body"><div class="label">{{ $k }}</div><div class="value {{ $k === 'Variance' && abs($v) > 0.01 ? 'text-danger' : '' }}">{{ money($v) }}</div></div></div></div>
    @endforeach
</div>
<div class="card mb-3"><div class="card-body d-flex flex-wrap gap-3 align-items-end">
    <a href="{{ route('checkoff.schedules.export', $schedule) }}" class="btn btn-outline-success"><i class="bi bi-download"></i> Export for employer (CSV)</a>
    <form method="POST" action="{{ route('checkoff.schedules.post', $schedule) }}" enctype="multipart/form-data" class="d-flex gap-2 align-items-end ms-auto">@csrf
        <div><label class="small">Employer's returned deductions (member_no,item,amount)</label><input type="file" name="file" accept=".csv" class="form-control form-control-sm" required></div>
        <div><label class="small">Posting date</label><input type="date" name="txn_date" value="{{ today()->toDateString() }}" class="form-control form-control-sm"></div>
        <button class="btn btn-success btn-sm">Post deductions</button>
    </form>
    <span class="badge text-bg-info">{{ $schedule->status }}</span>
</div></div>
@if(session('import_errors'))
    <div class="alert alert-danger small"><strong>{{ count(session('import_errors')) }} row(s) not posted</strong><ul class="mb-0">@foreach(session('import_errors') as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif
<div class="card"><div class="card-body">
    <table class="table table-sm table-hover">
        <thead><tr><th>Member</th><th>Payroll</th><th>Item</th><th class="text-end">Expected</th><th class="text-end">Received</th><th class="text-end">Variance</th></tr></thead>
        @foreach($schedule->lines->sortBy('member.member_no') as $l)
            <tr class="{{ $l->posted && abs($l->expected - $l->received) > 0.01 ? 'table-warning' : '' }}"><td>{{ $l->member->member_no }} {{ $l->member->full_name }}</td><td>{{ $l->member->payroll_no }}</td><td>{{ $l->item }}</td>
                <td class="text-money">{{ money($l->expected) }}</td><td class="text-money">{{ $l->posted ? money($l->received) : '—' }}</td>
                <td class="text-money">{{ $l->posted ? money($l->received - $l->expected) : '' }}</td></tr>
        @endforeach
    </table>
</div></div>
@endsection
