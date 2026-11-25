@extends('layouts.app')
@section('title', 'Role · ' . $role->label)
@section('content')
@php($me = auth()->user())
<form method="POST" action="{{ route('admin.roles.update', $role) }}">@csrf @method('PUT')
<div class="row g-3">
<div class="col-lg-4">
    <div class="card mb-3"><div class="card-body">
        <label class="form-label small mb-0">Role name</label>
        <input name="label" value="{{ old('label', $role->label) }}" class="form-control mb-2" @disabled($role->isLocked()) required>
        <label class="form-label small mb-0">Description</label>
        <textarea name="description" rows="2" class="form-control mb-2" @disabled($role->isLocked())>{{ old('description', $role->description) }}</textarea>
        <div class="small text-muted">Code: <code>{{ $role->name }}</code>@if($role->is_system) · built-in role @endif</div>
        @if($role->isLocked())<div class="alert alert-info small mt-2 mb-0">{{ $role->name === 'admin' ? 'The Administrator always has every permission.' : 'Members use the portal only.' }}</div>
        @else<button class="btn btn-primary w-100 mt-3"><i class="bi bi-save"></i> Save role</button>@endif
    </div></div>
    <div class="card mb-3"><div class="card-body">
        <h6>Users with this role ({{ $users->count() }})</h6>
        @forelse($users as $u)<div class="small"><a href="{{ route('admin.users.edit', $u) }}">{{ $u->name }}</a> @if($u->role !== $role->name)<span class="badge text-bg-light">additional</span>@endif @unless($u->is_active)<span class="badge text-bg-secondary">disabled</span>@endunless</div>
        @empty<div class="small text-muted">Nobody yet. Assign it under Staff users.</div>@endforelse
    </div></div>
    @unless($role->is_system)
        <button form="delRole" class="btn btn-outline-danger btn-sm w-100"><i class="bi bi-trash"></i> Delete role</button>
    @endunless
    <a href="{{ route('admin.roles.index') }}" class="btn btn-link w-100">Back to all roles</a>
</div>
<div class="col-lg-8">
    <div class="card"><div class="card-body">
        <div class="d-flex mb-2"><h6 class="mb-0">What this role can do</h6>
            @unless($role->isLocked())<span class="ms-auto small"><a href="#" onclick="document.querySelectorAll('.perm:not(:disabled)').forEach(c => c.checked = true); return false">select all</a> · <a href="#" onclick="document.querySelectorAll('.perm:not(:disabled)').forEach(c => c.checked = false); return false">clear</a></span>@endunless</div>
        <div class="row g-3">
        @foreach($catalogue as $group => $items)
            <div class="col-md-6"><div class="border rounded p-2 h-100">
                <div class="fw-semibold small text-uppercase mb-1">{{ $group }}
                    @unless($role->isLocked())<a href="#" class="float-end text-lowercase small" onclick="this.closest('.border').querySelectorAll('.perm:not(:disabled)').forEach(c => c.checked = !c.checked); return false">toggle</a>@endunless</div>
                @foreach($items as $perm => $label)
                    @php($on = $role->name === 'admin' || in_array($perm, $has))
                    <div class="form-check small">
                        <input type="checkbox" name="permissions[]" value="{{ $perm }}" class="form-check-input perm" id="p_{{ $perm }}" @checked($on)
                            @disabled($role->isLocked() || (! $me->isAdmin() && ! in_array($perm, $mine)))>
                        <label class="form-check-label" for="p_{{ $perm }}">{{ $label }}</label>
                        @if(! $role->isLocked() && ! $me->isAdmin() && ! in_array($perm, $mine) && $on)<input type="hidden" name="permissions[]" value="{{ $perm }}">@endif
                    </div>
                @endforeach
            </div></div>
        @endforeach
        </div>
    </div></div>
</div>
</div>
</form>
@unless($role->is_system)
<form method="POST" action="{{ route('admin.roles.destroy', $role) }}" id="delRole" data-confirm="Delete the role “{{ $role->label }}”?">@csrf @method('DELETE')</form>
@endunless
@endsection
