@extends('layouts.app')
@section('title', 'Staff Users')
@section('content')
<div class="card"><div class="card-body">
    <div class="d-flex flex-wrap gap-2 mb-3">
        <form class="d-flex gap-2"><select name="role" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">All roles</option>@foreach($roles as $r)<option value="{{ $r->name }}" @selected(request('role') === $r->name)>{{ $r->label }}</option>@endforeach</select></form>
        <a href="{{ route('admin.roles.index') }}" class="btn btn-sm btn-outline-secondary ms-auto"><i class="bi bi-shield-lock"></i> Roles &amp; permissions</a>
        <a href="{{ route('admin.users.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-person-plus"></i> New user</a>
    </div>
    <div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Role(s)</th><th>Branch</th><th>Last login</th><th>Status</th><th></th></tr></thead>
        @foreach($users as $u)
            <tr><td>{{ $u->name }}</td><td>{{ $u->email }}</td><td>{{ $u->phone }}</td>
                <td>{{ $u->role_label }}@foreach($u->extraRoles as $x)<span class="badge text-bg-light ms-1">+ {{ $x->label }}</span>@endforeach</td>
                <td class="small">{{ $u->branch?->name ?? 'Head office' }}</td>
                <td class="small">{{ $u->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                <td><span class="badge text-bg-{{ $u->is_active ? 'success' : 'secondary' }}">{{ $u->is_active ? 'Active' : 'Disabled' }}</span></td>
                <td>@if(! $u->isAdmin() || auth()->user()->isAdmin())<a href="{{ route('admin.users.edit', $u) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>@endif</td></tr>
        @endforeach
    </table>
    </div>
</div></div>
@endsection
