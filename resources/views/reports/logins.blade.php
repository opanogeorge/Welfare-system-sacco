@extends('layouts.app')
@section('title', 'Login History')
@section('content')
<div class="card"><div class="card-body">
    <div class="mb-2"><a href="?" class="btn btn-sm btn-outline-secondary">All</a> <a href="?failed=1" class="btn btn-sm btn-outline-danger">Failed only</a></div>
    <table class="table table-sm">
        <thead><tr><th>When</th><th>User</th><th>Event</th><th>IP</th><th>Details</th></tr></thead>
        @foreach($logs as $l)
            <tr class="{{ str_contains($l->action, 'failed') ? 'table-danger' : '' }}"><td>{{ $l->created_at->format('d/m/Y H:i:s') }}</td><td>{{ $l->user?->name ?? '—' }}</td>
                <td>{{ str_replace('_', ' ', $l->action) }}</td><td>{{ $l->ip }}</td><td class="small text-muted">{{ $l->data['login'] ?? ($l->data['agent'] ?? '') }}</td></tr>
        @endforeach
    </table>
    {{ $logs->links() }}
</div></div>
@endsection
