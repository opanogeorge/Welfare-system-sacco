@extends('layouts.app')
@section('title', 'Complaints & Inquiries')
@section('content')
<div class="row g-3">
<div class="col-lg-5"><div class="card"><div class="card-body">
    <form method="POST">@csrf
        <select name="category" class="form-select mb-2"><option value="inquiry">Inquiry</option><option value="complaint">Complaint</option><option value="suggestion">Suggestion</option></select>
        <input name="subject" class="form-control mb-2" placeholder="Subject" required>
        <textarea name="message" rows="5" class="form-control mb-2" placeholder="How can we help?" required></textarea>
        <button class="btn btn-success w-100">Submit</button>
    </form>
</div></div></div>
<div class="col-lg-7">
    @forelse($tickets as $t)
        <div class="card mb-2"><div class="card-body small">
            <div class="d-flex"><strong>{{ $t->ticket_no }} · {{ $t->subject }}</strong><span class="badge text-bg-light ms-auto">{{ str_replace('_', ' ', $t->status) }}</span></div>
            <p class="mb-1">{{ $t->message }}</p>
            @if($t->response)<div class="alert alert-success py-1 mb-0"><strong>SACCO:</strong> {{ $t->response }}</div>@endif
        </div></div>
    @empty <div class="card"><div class="card-body text-muted">No tickets yet.</div></div> @endforelse
</div>
</div>
@endsection
