@extends('layouts.app')
@section('title', 'Member Communication')
@section('content')
<div class="row g-3">
<div class="col-lg-5"><div class="card"><div class="card-body">
    <h6>Send bulk message</h6>
    <form method="POST" action="{{ route('messaging.send') }}" data-confirm="Send this message now?">@csrf
        <label class="form-label small">Audience</label>
        <select name="audience" class="form-select mb-2" onchange="document.querySelectorAll('.aud').forEach(e => e.classList.toggle('d-none', e.dataset.for !== this.value))">
            <option value="all">All active members</option><option value="arrears">Members with loans in arrears</option>
            <option value="employer">Members of an employer</option><option value="category">A member category</option>
            <option value="board">Board &amp; committee members</option><option value="member">One member</option>
        </select>
        <select name="employer_id" class="form-select mb-2 aud d-none" data-for="employer">@foreach($employers as $e)<option value="{{ $e->id }}">{{ $e->name }}</option>@endforeach</select>
        <select name="category_id" class="form-select mb-2 aud d-none" data-for="category">@foreach($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>
        <div class="aud d-none mb-2" data-for="member"><select name="member_id" class="member-select"></select></div>
        <label class="form-label small">Channels</label>
        <div class="mb-2 small">
            <label class="me-3"><input type="checkbox" name="channels[]" value="sms" checked> SMS</label>
            <label class="me-3"><input type="checkbox" name="channels[]" value="email"> Email</label>
            <label><input type="checkbox" name="channels[]" value="whatsapp"> WhatsApp</label>
        </div>
        <input name="subject" class="form-control mb-2" placeholder="Email subject (optional)">
        <textarea name="message" rows="5" class="form-control mb-1" maxlength="1000" placeholder="Dear {first_name}, ..." required oninput="cnt.textContent = this.value.length"></textarea>
        <div class="small text-muted mb-2"><span id="cnt">0</span> chars · placeholders: {name} {first_name} {member_no} {deposits} {shares} {savings} {loan_balance}</div>
        <button class="btn btn-success w-100"><i class="bi bi-send"></i> Send</button>
    </form>
    <p class="small text-muted mt-2 mb-0">Use this for AGM notices, contribution reminders, dividend announcements and general updates. Automatic messages (deposits, loan approvals, reminders, arrears) are sent by the system.</p>
</div></div></div>
<div class="col-lg-7"><div class="card"><div class="card-body">
    <div class="d-flex mb-2"><h6 class="mb-0">Message log</h6>
        <form class="ms-auto d-flex gap-1"><select name="channel" class="form-select form-select-sm"><option value="">All channels</option>@foreach(['sms', 'email', 'whatsapp'] as $c)<option @selected(request('channel') === $c)>{{ $c }}</option>@endforeach</select>
            <input name="batch" value="{{ request('batch') }}" class="form-control form-control-sm" placeholder="Batch"><button class="btn btn-sm btn-outline-secondary">Filter</button></form></div>
    <table class="table table-sm small">
        @foreach($logs as $l)
            <tr><td class="text-nowrap">{{ $l->created_at->format('d/m H:i') }}</td><td>{{ $l->channel }}</td><td>{{ $l->member?->member_no ?? $l->recipient }}</td>
                <td>{{ \Illuminate\Support\Str::limit($l->message, 90) }}</td>
                <td><span class="badge text-bg-{{ ['sent' => 'success', 'failed' => 'danger', 'logged' => 'secondary'][$l->status] }}">{{ $l->status }}</span></td></tr>
        @endforeach
    </table>
    {{ $logs->links() }}
    <p class="small text-muted mb-0">“logged” = channel not configured in .env yet (message written to the log only).</p>
</div></div></div>
</div>
@endsection
