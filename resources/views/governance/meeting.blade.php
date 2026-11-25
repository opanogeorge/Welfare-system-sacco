@extends('layouts.app')
@section('title', $meeting->title)
@section('content')
<div class="row g-3">
<div class="col-lg-7"><div class="card"><div class="card-body">
    <div class="small text-muted mb-2">{{ strtoupper($meeting->type) }} · {{ $meeting->scheduled_at->format('l d F Y, g:ia') }} · {{ $meeting->venue }}</div>
    <form method="POST" action="{{ route('governance.meetings.update', $meeting) }}">@csrf @method('PUT')
        <div class="row g-2 mb-2">
            <div class="col-md-4"><label class="small">Status</label><select name="status" class="form-select">@foreach(['scheduled', 'held', 'cancelled'] as $s)<option @selected($meeting->status === $s)>{{ $s }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="small">Attendance</label><input type="number" name="attendance" value="{{ $meeting->attendance }}" class="form-control"></div>
        </div>
        <label class="small">Agenda</label><textarea name="agenda" rows="4" class="form-control mb-2">{{ $meeting->agenda }}</textarea>
        <label class="small">Minutes</label><textarea name="minutes" rows="14" class="form-control mb-2" placeholder="Record minutes here…">{{ $meeting->minutes }}</textarea>
        <button class="btn btn-success">Save</button> <button type="button" onclick="print()" class="btn btn-outline-secondary"><i class="bi bi-printer"></i></button>
    </form>
</div></div></div>
<div class="col-lg-5"><div class="card"><div class="card-body">
    <h6>Resolutions</h6>
    @foreach($meeting->resolutions as $r)
        <div class="border rounded p-2 mb-2 small">
            <div class="d-flex"><strong>{{ $r->number }} · {{ $r->title }}</strong>
                <form method="POST" action="{{ route('governance.resolutions.update', $r) }}" class="ms-auto">@csrf @method('PUT')
                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">@foreach(['passed', 'rejected', 'deferred', 'implemented'] as $s)<option @selected($r->status === $s)>{{ $s }}</option>@endforeach</select></form></div>
            {{ $r->details }}
        </div>
    @endforeach
    <form method="POST" action="{{ route('governance.resolutions.store', $meeting) }}" class="mt-3">@csrf
        <div class="row g-1 mb-1"><div class="col-4"><input name="number" class="form-control form-control-sm" placeholder="No. e.g. BR/12/2026" required></div><div class="col-8"><input name="title" class="form-control form-control-sm" placeholder="Title" required></div></div>
        <textarea name="details" class="form-control form-control-sm mb-1" rows="2" placeholder="Details"></textarea>
        <div class="d-flex gap-1"><select name="status" class="form-select form-select-sm"><option>passed</option><option>rejected</option><option>deferred</option></select><button class="btn btn-sm btn-success">Add</button></div>
    </form>
</div></div></div>
</div>
@endsection
