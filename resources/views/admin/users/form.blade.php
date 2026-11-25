@extends('layouts.app')
@section('title', $user->exists ? "Edit {$user->name}" : 'New Staff User')
@section('content')
<div class="card col-lg-6"><div class="card-body">
<form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}">
    @csrf @if($user->exists) @method('PUT') @endif
    <div class="mb-2"><label class="form-label">Name</label><input name="name" value="{{ old('name', $user->name) }}" class="form-control" required></div>
    <div class="mb-2"><label class="form-label">Email</label><input type="email" name="email" value="{{ old('email', $user->email) }}" class="form-control" required></div>
    <div class="mb-2"><label class="form-label">Phone</label><input name="phone" value="{{ old('phone', $user->phone) }}" class="form-control"></div>
    <div class="mb-2"><label class="form-label">Role</label><select name="role" class="form-select">
        @foreach(\App\Models\User::ROLES as $k => $v) @continue($k === 'member')<option value="{{ $k }}" @selected(old('role', $user->role) === $k)>{{ $v }}</option>@endforeach</select></div>
    <div class="mb-2"><label class="form-label">Branch</label><select name="branch_id" class="form-select">
        <option value="">Head office (sees all branches)</option>
        @foreach(\App\Models\Branch::where('is_active', true)->orderBy('name')->get() as $b)<option value="{{ $b->id }}" @selected(old('branch_id', $user->branch_id) == $b->id)>{{ $b->name }}</option>@endforeach
    </select></div>
    <div class="mb-2"><label class="form-label">Password {{ $user->exists ? '(leave blank to keep)' : '' }}</label><input type="password" name="password" class="form-control" @required(! $user->exists)></div>
    <div class="form-check mb-3"><input type="checkbox" name="is_active" value="1" class="form-check-input" id="ia" @checked(old('is_active', $user->is_active))><label class="form-check-label" for="ia">Active</label></div>
    <button class="btn btn-success">Save</button> <a href="{{ route('admin.users.index') }}" class="btn btn-link">Cancel</a>
</form>
<hr><p class="small text-muted mb-0"><strong>Roles:</strong> Teller posts deposits, withdrawals and repayments · Loan officer captures and appraises loans · Manager approves loans, member status and dividends · Accountant disburses loans, records expenses and runs dividends · Administrator can do everything.</p>
</div></div>
@endsection
