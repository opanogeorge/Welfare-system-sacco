@extends('layouts.app')
@section('title', 'Member Register')
@section('content')
<div class="row g-3 mb-3">
    @foreach(['Members' => $stats['total'], 'Male' => $stats['male'], 'Female' => $stats['female'], 'No ID on file' => $stats['no_kyc'], 'High risk / PEP' => $stats['high_risk']] as $k => $v)
        <div class="col"><div class="card stat"><div class="card-body"><div class="label">{{ $k }}</div><div class="value">{{ number_format($v) }}</div></div></div></div>
    @endforeach
</div>
<div class="card"><div class="card-body">
    <form class="d-flex gap-2 mb-3 no-print"><select name="status" class="form-select w-auto"><option value="">All statuses</option>@foreach(['pending', 'active', 'dormant', 'suspended', 'exited'] as $s)<option @selected(request('status') === $s)>{{ $s }}</option>@endforeach</select>
        <button class="btn btn-outline-secondary">Filter</button><a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn btn-outline-secondary ms-auto"><i class="bi bi-download"></i> CSV (full register)</a></form>
    <div class="table-responsive">
    <table class="table table-sm table-hover">
        <thead><tr><th>Member No</th><th>Name</th><th>ID</th><th>Gender</th><th>Phone</th><th>Employer</th><th>Category</th><th>Joined</th><th>Status</th><th>KYC</th></tr></thead>
        @foreach($rows as $m)
            <tr><td><a href="{{ route('members.show', $m) }}">{{ $m->member_no }}</a></td><td>{{ $m->full_name }}</td><td>{{ $m->national_id }}</td><td>{{ $m->gender }}</td><td>{{ $m->phone }}</td>
                <td class="small">{{ $m->employerRecord?->name ?? $m->employer }}</td><td class="small">{{ $m->category?->name }}</td><td>{{ $m->joined_on->format('d/m/Y') }}</td><td>{{ $m->status }}</td>
                <td>@if($m->kyc_docs)<i class="bi bi-check-circle text-success"></i>@else<i class="bi bi-x-circle text-danger"></i>@endif @if($m->is_pep)<span class="badge text-bg-warning">PEP</span>@endif</td></tr>
        @endforeach
    </table>
    </div>
</div></div>
@endsection
