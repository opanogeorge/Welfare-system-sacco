@extends('layouts.app')
@section('title', 'Branches')
@section('content')
<div class="row g-3">
<div class="col-lg-8"><div class="card"><div class="card-body">
    <table class="table align-middle">
        <thead><tr><th>Code</th><th>Branch</th><th>Location</th><th class="text-end">Members</th><th class="text-end">Staff</th><th>Status</th><th></th></tr></thead>
        @forelse($branches as $b)
            <tr><td>{{ $b->code }}</td><td>{{ $b->name }} @if($b->is_hq)<span class="badge text-bg-info">HQ</span>@endif</td><td>{{ $b->location }}</td>
                <td class="text-money">{{ $b->members_count }}</td><td class="text-money">{{ $b->users_count }}</td>
                <td><span class="badge text-bg-{{ $b->is_active ? 'success' : 'secondary' }}">{{ $b->is_active ? 'Active' : 'Closed' }}</span></td>
                <td><button class="btn btn-sm btn-link" data-bs-toggle="collapse" data-bs-target="#b{{ $b->id }}"><i class="bi bi-pencil"></i></button></td></tr>
            <tr class="collapse" id="b{{ $b->id }}"><td colspan="7">
                <form method="POST" action="{{ route('admin.branches.update', $b) }}" class="row g-1">@csrf @method('PUT')
                    <div class="col-2"><input name="code" value="{{ $b->code }}" class="form-control form-control-sm"></div>
                    <div class="col-3"><input name="name" value="{{ $b->name }}" class="form-control form-control-sm"></div>
                    <div class="col-3"><input name="location" value="{{ $b->location }}" class="form-control form-control-sm"></div>
                    <div class="col-2"><input name="phone" value="{{ $b->phone }}" class="form-control form-control-sm"></div>
                    <div class="col-2 small"><label><input type="checkbox" name="is_hq" value="1" @checked($b->is_hq)> HQ</label> <label><input type="checkbox" name="is_active" value="1" @checked($b->is_active)> Active</label></div>
                    <div class="col-12"><button class="btn btn-sm btn-success">Save</button></div>
                </form>
            </td></tr>
        @empty <tr><td colspan="7" class="text-muted">No branches yet – the SACCO runs as a single office.</td></tr> @endforelse
    </table>
</div></div></div>
<div class="col-lg-4"><div class="card"><div class="card-body">
    <h6>Add branch</h6>
    <form method="POST" action="{{ route('admin.branches.store') }}">@csrf
        <input name="code" class="form-control mb-2" placeholder="Code e.g. KSM" required>
        <input name="name" class="form-control mb-2" placeholder="Name e.g. Kisumu Branch" required>
        <input name="location" class="form-control mb-2" placeholder="Location">
        <input name="phone" class="form-control mb-2" placeholder="Phone">
        <label class="small mb-2"><input type="checkbox" name="is_hq" value="1"> Head office</label><input type="hidden" name="is_active" value="1">
        <button class="btn btn-success w-100">Add</button>
    </form>
    <hr>
    <ul class="small text-muted ps-3 mb-0">
        <li>Assign each staff user to a branch (Staff Users). Users with no branch are <strong>head office</strong> and see everything.</li>
        <li>Branch staff see only their branch in lists and reports; members can still transact at any branch.</li>
        <li>New members, loans, transactions, journals and expenses are tagged with the branch automatically, so branch P&amp;L, PAR and cash are available in Analytics.</li>
        <li>GL, dividends, integrations and the risk model are head-office functions.</li>
    </ul>
</div></div></div>
</div>
@endsection
