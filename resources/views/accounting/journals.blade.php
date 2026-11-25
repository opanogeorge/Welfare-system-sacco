@extends('layouts.app')
@section('title', 'Journal Entries')
@section('content')
@include('accounting._nav')
<div class="card"><div class="card-body">
    <form class="row g-2 mb-3">
        <div class="col-md-4"><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Entry no or description"></div>
        <div class="col-md-2"><select name="status" class="form-select"><option value="">All</option>@foreach(['draft','posted','reversed'] as $s)<option @selected(request('status') === $s)>{{ $s }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="small"><input type="checkbox" name="manual" value="1" @checked(request('manual'))> Manual only</label></div>
        <div class="col-md-4 d-flex gap-2"><button class="btn btn-outline-secondary"><i class="bi bi-search"></i></button>
            @if(auth()->user()->hasRole('accountant'))<a href="{{ route('accounting.journals.create') }}" class="btn btn-success ms-auto"><i class="bi bi-plus"></i> Manual journal</a>@endif</div>
    </form>
    <table class="table table-sm table-hover">
        <thead><tr><th>Date</th><th>Entry</th><th>Description</th><th>Source</th><th class="text-end">Amount</th><th>Status</th><th>By</th></tr></thead>
        @foreach($entries as $e)
            <tr><td>{{ $e->entry_date->format('d/m/Y') }}</td><td><a href="{{ route('accounting.journals.show', $e) }}">{{ $e->entry_no }}</a></td>
                <td class="small">{{ $e->description }}</td><td class="small text-muted">{{ $e->source_type ? class_basename($e->source_type) : 'Manual' }}</td>
                <td class="text-money">{{ money($e->lines->sum('debit')) }}</td>
                <td><span class="badge text-bg-{{ ['posted' => 'success', 'draft' => 'warning', 'reversed' => 'secondary'][$e->status] }}">{{ $e->status }}</span></td>
                <td class="small">{{ $e->creator?->name ?? 'System' }}</td></tr>
        @endforeach
    </table>
    {{ $entries->links() }}
</div></div>
@endsection
