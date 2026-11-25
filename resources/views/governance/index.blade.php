@extends('layouts.app')
@section('title', 'Governance')
@section('content')
<div class="row g-3">
<div class="col-lg-7">
    <div class="card mb-3"><div class="card-body">
        <h6>Meetings</h6>
        <table class="table table-sm table-hover">
            <thead><tr><th>Date</th><th>Type</th><th>Title</th><th>Resolutions</th><th>Status</th></tr></thead>
            @forelse($meetings as $m)
                <tr><td>{{ $m->scheduled_at->format('d/m/Y H:i') }}</td><td>{{ strtoupper($m->type) }}</td><td><a href="{{ route('governance.meetings.show', $m) }}">{{ $m->title }}</a></td>
                    <td>{{ $m->resolutions_count }}</td><td><span class="badge text-bg-light">{{ $m->status }}</span></td></tr>
            @empty <tr><td colspan="5" class="text-muted">No meetings yet.</td></tr> @endforelse
        </table>
        {{ $meetings->links() }}
    </div></div>
    <div class="card"><div class="card-body">
        <h6>Schedule a meeting</h6>
        <form method="POST" action="{{ route('governance.meetings.store') }}" class="row g-2">@csrf
            <div class="col-md-3"><select name="type" class="form-select"><option value="board">Board</option><option value="committee">Committee</option><option value="agm">AGM</option><option value="sgm">SGM</option></select></div>
            <div class="col-md-5"><input name="title" class="form-control" placeholder="Title" required></div>
            <div class="col-md-4"><input type="datetime-local" name="scheduled_at" class="form-control" required></div>
            <div class="col-md-12"><input name="venue" class="form-control" placeholder="Venue"></div>
            <div class="col-12"><textarea name="agenda" class="form-control" rows="3" placeholder="Agenda"></textarea></div>
            <div class="col-12 d-flex align-items-center"><label class="small"><input type="checkbox" name="notify" value="1" checked> Send notice by SMS/email (AGM/SGM → all members; others → committee members)</label>
                <button class="btn btn-success ms-auto">Schedule</button></div>
        </form>
    </div></div>
</div>
<div class="col-lg-5">
    <div class="card mb-3"><div class="card-body">
        <h6>Board &amp; committees</h6>
        @forelse($committees as $c => $list)
            <div class="fw-semibold small mt-2">{{ $names[$c] ?? $c }}</div>
            <table class="table table-sm small mb-1">@foreach($list as $cm)<tr><td>{{ $cm->member->full_name }}</td><td>{{ $cm->position }}</td><td class="text-muted">since {{ $cm->from_date->format('M Y') }}</td>
                <td><form method="POST" action="{{ route('governance.committee.end', $cm) }}" data-confirm="End this term?">@csrf<button class="btn btn-sm btn-link p-0 text-danger">end</button></form></td></tr>@endforeach</table>
        @empty <p class="text-muted small">No committee members recorded.</p> @endforelse
    </div></div>
    <div class="card"><div class="card-body">
        <h6>Add committee member</h6>
        <form method="POST" action="{{ route('governance.committee.store') }}">@csrf
            <select name="member_id" class="member-select" required></select>
            <select name="committee" class="form-select my-2">@foreach($names as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
            <input name="position" class="form-control mb-2" placeholder="Position e.g. Chairperson, Secretary, Member" required>
            <div class="row g-2 mb-2"><div class="col"><input type="date" name="from_date" value="{{ today()->toDateString() }}" class="form-control"></div><div class="col"><input type="date" name="to_date" class="form-control" title="Term ends"></div></div>
            <button class="btn btn-success w-100">Add</button>
        </form>
        <p class="small text-muted mt-2 mb-0">Elections: your Staff e-Voting system can run the ballot; record the winners here.</p>
    </div></div>
</div>
</div>
@endsection
