@extends('layouts.app')
@section('title', 'Staff Users')
@section('content')
<div class="card"><div class="card-body">
    <div class="d-flex mb-3"><a href="{{ route('admin.users.create') }}" class="btn btn-success ms-auto"><i class="bi bi-person-plus"></i> New user</a></div>
    <table class="table table-hover align-middle">
        <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Branch</th><th>Last login</th><th>Status</th><th></th></tr></thead>
        @foreach($users as $u)
            <tr><td>{{ $u->name }}</td><td>{{ $u->email }}</td><td>{{ $u->phone }}</td><td>{{ \App\Models\User::ROLES[$u->role] }}</td><td class="small">{{ $u->branch?->name ?? 'Head office' }}</td>
                <td class="small">{{ $u->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                <td><span class="badge text-bg-{{ $u->is_active ? 'success' : 'secondary' }}">{{ $u->is_active ? 'Active' : 'Disabled' }}</span></td>
                <td><a href="{{ route('admin.users.edit', $u) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a></td></tr>
        @endforeach
    </table>
</div></div>
@endsection
