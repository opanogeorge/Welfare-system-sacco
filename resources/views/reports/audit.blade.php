@extends('layouts.app')
@section('title', 'Audit Trail')
@section('content')
<div class="card"><div class="card-body">
    <form class="mb-3 d-flex gap-2"><input name="action" value="{{ request('action') }}" class="form-control w-auto" placeholder="Action e.g. loan_approved"><button class="btn btn-outline-secondary">Filter</button></form>
    <table class="table table-sm">
        <thead><tr><th>When</th><th>User</th><th>Action</th><th>Subject</th><th>Details</th><th>IP</th></tr></thead>
        @foreach($logs as $l)
            <tr><td class="small">{{ $l->created_at->format('d/m/Y H:i:s') }}</td><td>{{ $l->user?->name ?? 'System' }}</td><td><code>{{ $l->action }}</code></td>
                <td class="small">{{ class_basename($l->subject_type) }} #{{ $l->subject_id }}</td><td class="small text-muted">{{ $l->data ? json_encode($l->data) : '' }}</td><td class="small">{{ $l->ip }}</td></tr>
        @endforeach
    </table>
    {{ $logs->links() }}
</div></div>
@endsection
