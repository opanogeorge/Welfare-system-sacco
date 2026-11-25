@extends('layouts.app')
@section('title', 'Automatic Reminders')
@section('content')
@php($canEdit = can_route('reminders.store'))
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="card stat h-100"><div class="card-body"><div class="label">Active rules</div><div class="value">{{ $rules->where('is_active', true)->count() }} / {{ $rules->count() }}</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card stat h-100"><div class="card-body"><div class="label">Sent today</div><div class="value">{{ $stats['today'] }}</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card stat h-100"><div class="card-body"><div class="label">Last 7 days</div><div class="value">{{ $stats['week'] }}</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card stat h-100"><div class="card-body"><div class="label">Failed (7 days)</div><div class="value {{ $stats['failed'] ? 'text-danger' : '' }}">{{ $stats['failed'] }}</div></div></div></div>
</div>

<div class="card mb-3"><div class="card-body">
    <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
        <h6 class="mb-0">Reminder rules</h6>
        <span class="small text-muted">Run automatically every morning at 08:00. Each reminder goes to a member only once.</span>
        <div class="ms-auto d-flex gap-2">
            <a href="{{ route('reminders.index', ['preview' => 1]) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i> Preview today's</a>
            @if($canEdit)
                <form method="POST" action="{{ route('reminders.run') }}" data-confirm="Send all reminders that are due today now?">@csrf<button class="btn btn-sm btn-primary"><i class="bi bi-send"></i> Send due reminders now</button></form>
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#newRule"><i class="bi bi-plus"></i> Add rule</button>
            @endif
        </div>
    </div>

    @if($canEdit)
    <form method="POST" action="{{ route('reminders.store') }}" class="collapse border rounded p-3 mb-3" id="newRule">@csrf
        @include('reminders._fields', ['r' => new \App\Models\ReminderRule(['channel' => 'sms', 'is_active' => true, 'event' => 'loan_overdue', 'days' => 3])])
        <button class="btn btn-sm btn-success mt-2">Add rule</button>
    </form>
    @endif

    <div class="table-responsive">
    <table class="table table-sm align-middle">
        <thead><tr><th>Rule</th><th>When</th><th>Message</th><th>Channel</th><th class="text-end">Sent (30d)</th><th></th></tr></thead>
        @foreach($rules as $r)
            @php($ev = \App\Services\ReminderService::EVENTS[$r->event] ?? [$r->event, ''])
            <tr class="{{ $r->is_active ? '' : 'text-muted' }}">
                <td class="fw-semibold">{{ $r->name }} @if($r->notify_guarantors)<span class="badge text-bg-warning" title="Guarantors also get an SMS">+ guarantors</span>@endif</td>
                <td class="small">{{ $ev[0] }}<br><span class="text-muted">{{ $r->days }} {{ $ev[1] }}</span></td>
                <td class="small" style="max-width:420px">{{ $r->message }}</td>
                <td><span class="badge text-bg-light">{{ $r->channel }}</span></td>
                <td class="text-end">{{ $r->sent_30 }}</td>
                <td class="text-nowrap text-end">
                    @if($canEdit)
                        <form method="POST" action="{{ route('reminders.toggle', $r) }}" class="d-inline">@csrf
                            <button class="btn btn-sm {{ $r->is_active ? 'btn-success' : 'btn-outline-secondary' }}" title="Switch on/off">{{ $r->is_active ? 'On' : 'Off' }}</button></form>
                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#rule{{ $r->id }}"><i class="bi bi-pencil"></i></button>
                        <form method="POST" action="{{ route('reminders.destroy', $r) }}" class="d-inline" data-confirm="Delete this rule?">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                    @else
                        <span class="badge text-bg-{{ $r->is_active ? 'success' : 'secondary' }}">{{ $r->is_active ? 'On' : 'Off' }}</span>
                    @endif
                </td>
            </tr>
            @if($canEdit)
            <tr class="collapse" id="rule{{ $r->id }}"><td colspan="6" class="bg-light">
                <form method="POST" action="{{ route('reminders.update', $r) }}" class="p-2">@csrf @method('PUT')
                    @include('reminders._fields', ['r' => $r])
                    <button class="btn btn-sm btn-success mt-2">Save</button>
                </form>
            </td></tr>
            @endif
        @endforeach
    </table>
    </div>
    <div class="small text-muted">Placeholders: @foreach(\App\Services\ReminderService::PLACEHOLDERS as $p)<code>{{ $p }}</code> @endforeach</div>
</div></div>

@if($preview !== null)
<div class="card mb-3 border-primary"><div class="card-body">
    <h6>Due to go out today <small class="text-muted">({{ count($preview) }}, not yet sent)</small></h6>
    <table class="table table-sm small mb-0">
        @forelse($preview as $p)
            <tr><td class="text-nowrap">{{ $p['rule'] }}</td><td class="text-nowrap">{{ $p['member']->member_no }} {{ $p['member']->full_name }}<br><span class="text-muted">{{ $p['member']->phone }}</span></td><td>{{ $p['text'] }}</td></tr>
        @empty<tr><td class="text-muted">Nothing due today, or everything has already been sent.</td></tr>@endforelse
    </table>
</div></div>
@endif

<div class="card"><div class="card-body">
    <form class="row g-2 mb-2">
        <div class="col-md-4"><input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Member no. or name"></div>
        <div class="col-md-3"><select name="rule" class="form-select form-select-sm"><option value="">All rules</option>@foreach($rules as $r)<option value="{{ $r->id }}" @selected(request('rule') == $r->id)>{{ $r->name }}</option>@endforeach</select></div>
        <div class="col-md-2"><select name="status" class="form-select form-select-sm"><option value="">Any status</option><option value="sent" @selected(request('status') === 'sent')>Sent</option><option value="failed" @selected(request('status') === 'failed')>Failed</option></select></div>
        <div class="col-md-2"><button class="btn btn-sm btn-outline-secondary">Filter</button></div>
    </form>
    <h6>Reminder log</h6>
    <div class="table-responsive">
    <table class="table table-sm small">
        <thead><tr><th>When</th><th>To</th><th>Loan</th><th>Rule</th><th>Message</th><th>Status</th></tr></thead>
        @forelse($logs as $l)
            <tr><td class="text-nowrap">{{ $l->created_at->format('d/m H:i') }}</td>
                <td>{{ $l->member?->member_no }} {{ $l->member?->full_name }} @if($l->recipient === 'guarantor')<span class="badge text-bg-warning">guarantor</span>@endif</td>
                <td>@if($l->loan)<a href="{{ route('loans.show', $l->loan) }}">{{ $l->loan->loan_no }}</a>@endif</td>
                <td>{{ $l->rule?->name ?? ('Manual · ' . ($l->sender?->name ?? '')) }}</td>
                <td>{{ $l->message }}</td>
                <td><span class="badge text-bg-{{ $l->status === 'sent' ? 'success' : 'danger' }}">{{ $l->status }}</span></td></tr>
        @empty<tr><td colspan="6" class="text-muted">No reminders sent yet.</td></tr>@endforelse
    </table>
    </div>
    {{ $logs->links() }}
</div></div>
@endsection
