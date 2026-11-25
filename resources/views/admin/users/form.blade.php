@extends('layouts.app')
@section('title', $user->exists ? "Edit {$user->name}" : 'New Staff User')
@section('content')
@php($self = $user->exists && $user->id === auth()->id())
<div class="row g-3">
<div class="col-lg-6"><div class="card"><div class="card-body">
<form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}">
    @csrf @if($user->exists) @method('PUT') @endif
    <div class="mb-2"><label class="form-label">Name</label><input name="name" value="{{ old('name', $user->name) }}" class="form-control" required></div>
    <div class="mb-2"><label class="form-label">Email</label><input type="email" name="email" value="{{ old('email', $user->email) }}" class="form-control" required></div>
    <div class="mb-2"><label class="form-label">Phone</label><input name="phone" value="{{ old('phone', $user->phone) }}" class="form-control"></div>
    <div class="mb-2"><label class="form-label">Main role</label>
        <select name="role" class="form-select" id="mainRole" @disabled($self)>
            @foreach($roles as $r)<option value="{{ $r->name }}" @selected(old('role', $user->role) === $r->name)>{{ $r->label }}</option>@endforeach
        </select>
        @if($self)<input type="hidden" name="role" value="{{ $user->role }}"><div class="form-text">You can't change your own role.</div>@endif</div>
    <div class="mb-2"><label class="form-label">Additional roles <small class="text-muted">(optional)</small></label>
        <div class="border rounded p-2" style="max-height:170px;overflow:auto">
            @php($extra = old('extra_roles', $user->exists ? $user->extraRoles->pluck('id')->all() : []))
            @foreach($roles as $r)@continue($r->name === 'admin')
                <div class="form-check"><input type="checkbox" name="extra_roles[]" value="{{ $r->id }}" class="form-check-input" id="xr{{ $r->id }}" @checked(in_array($r->id, $extra)) @disabled($self)>
                    <label class="form-check-label small" for="xr{{ $r->id }}">{{ $r->label }}</label></div>
            @endforeach
        </div>
        <div class="form-text">The user gets every permission from the main role plus these roles, e.g. a teller who also records welfare contributions.</div></div>
    <div class="mb-2"><label class="form-label">Branch</label><select name="branch_id" class="form-select">
        <option value="">Head office (sees all branches)</option>
        @foreach(\App\Models\Branch::where('is_active', true)->orderBy('name')->get() as $b)<option value="{{ $b->id }}" @selected(old('branch_id', $user->branch_id) == $b->id)>{{ $b->name }}</option>@endforeach
    </select></div>
    <div class="mb-2"><label class="form-label">Password {{ $user->exists ? '(leave blank to keep)' : '' }}</label><input type="password" name="password" class="form-control" @required(! $user->exists) autocomplete="new-password"></div>
    <div class="form-check mb-3"><input type="checkbox" name="is_active" value="1" class="form-check-input" id="ia" @checked(old('is_active', $user->is_active)) @disabled($self)><label class="form-check-label" for="ia">Active</label></div>
    @if($self)<input type="hidden" name="is_active" value="1">@endif
    <button class="btn btn-primary">Save</button> <a href="{{ route('admin.users.index') }}" class="btn btn-link">Cancel</a>
</form>
</div></div></div>
<div class="col-lg-6">
    @if($user->exists)
    <div class="card"><div class="card-body">
        <h6>What {{ $user->name }} can do now</h6>
        @if($user->isAdmin())<p class="small mb-0">Administrator: every permission.</p>@else
        @php($has = $user->permissionNames())
        @foreach(\App\Support\Permissions::catalogue() as $group => $items)
            @php($g = array_intersect_key($items, array_flip($has)))
            @if($g)<div class="small mb-2"><strong>{{ $group }}</strong><br>{{ implode(' · ', $g) }}</div>@endif
        @endforeach
        @if(! $has)<p class="small text-muted mb-0">No permissions. The user can only see the dashboard.</p>@endif
        @endif
    </div></div>
    @endif
    <p class="small text-muted mt-2">Roles and what each can do are managed under <a href="{{ route('admin.roles.index') }}">Roles &amp; permissions</a>.</p>
</div>
</div>
@endsection
