@extends('layouts.app')
@section('title', 'Complaints & Inquiries')
@section('content')
<div class="row g-3">
<div class="col-lg-8">
    <div class="mb-2">@foreach(['' => 'Open', 'resolved' => 'Resolved', 'closed' => 'Closed'] as $k => $v)<a href="?status={{ $k }}" class="btn btn-sm {{ request('status', '') === $k ? 'btn-success' : 'btn-outline-secondary' }} me-1">{{ $v }}</a>@endforeach</div>
    @forelse($tickets as $t)
        <div class="card mb-2"><div class="card-body">
            <div class="d-flex"><strong>{{ $t->ticket_no }} · {{ $t->subject }}</strong><span class="badge text-bg-{{ $t->category === 'complaint' ? 'danger' : 'info' }} ms-2">{{ $t->category }}</span>
                <small class="ms-auto text-muted">{{ $t->created_at->diffForHumans() }}</small></div>
            <div class="small text-muted mb-2">{{ $t->member ? $t->member->member_no . ' ' . $t->member->full_name . ' · ' . $t->member->phone : 'Walk-in / anonymous' }}</div>
            <p class="mb-2">{!! nl2br(e($t->message)) !!}</p>
            @if($t->response)<div class="alert alert-light small py-2 mb-2"><strong>Response:</strong> {!! nl2br(e($t->response)) !!}</div>@endif
            <form method="POST" action="{{ route('service.tickets.update', $t) }}" class="row g-1">@csrf @method('PUT')
                <div class="col-md-3"><select name="status" class="form-select form-select-sm">@foreach(['open', 'in_progress', 'resolved', 'closed'] as $s)<option value="{{ $s }}" @selected($t->status === $s)>{{ str_replace('_', ' ', $s) }}</option>@endforeach</select></div>
                <div class="col-md-3"><select name="assigned_to" class="form-select form-select-sm"><option value="">Unassigned</option>@foreach($staff as $s)<option value="{{ $s->id }}" @selected($t->assigned_to === $s->id)>{{ $s->name }}</option>@endforeach</select></div>
                <div class="col-md-5"><input name="response" class="form-control form-control-sm" placeholder="Reply to member (sent by SMS/email)"></div>
                <div class="col-md-1"><button class="btn btn-sm btn-success w-100">Save</button></div>
            </form>
        </div></div>
    @empty <div class="card"><div class="card-body text-muted">No tickets.</div></div> @endforelse
    {{ $tickets->links() }}
</div>
<div class="col-lg-4"><div class="card"><div class="card-body">
    <h6>Log a ticket (walk-in / phone)</h6>
    <form method="POST">@csrf
        <select name="member_id" class="member-select"></select>
        <select name="category" class="form-select my-2"><option>complaint</option><option>inquiry</option><option>suggestion</option></select>
        <input name="subject" class="form-control mb-2" placeholder="Subject" required>
        <textarea name="message" class="form-control mb-2" rows="4" placeholder="Details" required></textarea>
        <button class="btn btn-success w-100">Log</button>
    </form>
</div></div></div>
</div>
@endsection
