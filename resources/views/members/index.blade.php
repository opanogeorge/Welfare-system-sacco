@extends('layouts.app')
@section('title', 'Members')
@section('content')
<div class="card"><div class="card-body">
    <form class="row g-2 mb-3">
        <div class="col-md-5"><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Search member no, name, ID or phone"></div>
        <div class="col-md-3">
            <select name="status" class="form-select"><option value="">All statuses</option>
                @foreach(['pending', 'active', 'dormant', 'suspended', 'exited'] as $s)<option @selected(request('status') === $s)>{{ $s }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-4 d-flex gap-2">
            <button class="btn btn-outline-secondary"><i class="bi bi-search"></i></button>
            <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn btn-outline-secondary"><i class="bi bi-download"></i> CSV</a>
            <a href="{{ route('members.create') }}" class="btn btn-success ms-auto"><i class="bi bi-person-plus"></i> Register member</a>
        </div>
    </form>
    <div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead><tr><th>Member No</th><th>Name</th><th>ID No</th><th>Phone</th><th>Payroll</th><th>Joined</th><th>Status</th></tr></thead>
        @forelse($members as $m)
            <tr>
                <td><a href="{{ route('members.show', $m) }}" class="fw-semibold">{{ $m->member_no }}</a></td>
                <td>{{ $m->full_name }}</td><td>{{ $m->national_id }}</td><td>{{ $m->phone }}</td><td>{{ $m->payroll_no }}</td>
                <td>{{ $m->joined_on->format('d/m/Y') }}</td>
                <td><span class="badge text-bg-{{ ['active' => 'success', 'pending' => 'warning', 'dormant' => 'secondary', 'suspended' => 'danger', 'exited' => 'dark'][$m->status] }}">{{ $m->status }}</span></td>
            </tr>
        @empty <tr><td colspan="7" class="text-muted text-center py-4">No members found.</td></tr> @endforelse
    </table>
    </div>
    {{ $members->links() }}
</div></div>
@endsection
