@extends('layouts.app')
@section('title', 'Change Password')
@section('content')
<div class="card col-md-6 col-lg-5">
    <div class="card-body">
        <form method="POST">
            @csrf @method('PUT')
            <div class="mb-3"><label class="form-label">Current password</label><input type="password" name="current_password" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">New password</label><input type="password" name="password" class="form-control" required>
                <div class="form-text">At least 8 characters with letters and numbers.</div></div>
            <div class="mb-3"><label class="form-label">Confirm new password</label><input type="password" name="password_confirmation" class="form-control" required></div>
            <button class="btn btn-success">Update password</button>
        </form>
    </div>
</div>
@endsection
