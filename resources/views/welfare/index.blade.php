@extends('layouts.app')
@section('title', 'Welfare')
@section('content')
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-2"><div class="card stat h-100"><div class="card-body"><div class="label">Welfare fund balance</div><div class="value">{{ money($stats['fund'], 0) }}</div></div></div></div>
    <div class="col-6 col-lg-2"><div class="card stat h-100"><div class="card-body"><div class="label">Open events</div><div class="value">{{ $stats['open'] }}</div></div></div></div>
    <div class="col-6 col-lg-2"><div class="card stat h-100"><div class="card-body"><div class="label">Requests to review</div><div class="value {{ $stats['requested'] ? 'text-warning' : '' }}">{{ $stats['requested'] }}</div></div></div></div>
    <div class="col-6 col-lg-2"><div class="card stat h-100"><div class="card-body"><div class="label">Unpaid levies</div><div class="value text-danger">{{ money($stats['due'], 0) }}</div></div></div></div>
    <div class="col-6 col-lg-2"><div class="card stat h-100"><div class="card-body"><div class="label">Paid out {{ now()->year }}</div><div class="value">{{ money($stats['year_paid'], 0) }}</div></div></div></div>
    <div class="col-6 col-lg-2"><div class="card stat h-100"><div class="card-body"><div class="label">Monthly fund ({{ now()->format('M') }})</div><div class="value">{{ money($stats['monthly'], 0) }}</div></div></div></div>
</div>
<div class="card"><div class="card-body">
    <div class="d-flex flex-wrap gap-2 mb-3">
        <form class="d-flex gap-2">
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">All statuses</option>@foreach(\App\Models\WelfareEvent::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach</select>
            <select name="type" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">All types</option>@foreach(\App\Models\WelfareEvent::TYPES as $k => $v)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $v }}</option>@endforeach</select>
        </form>
        <a href="{{ route('welfare.create') }}" class="btn btn-sm btn-primary ms-auto"><i class="bi bi-plus-circle"></i> New welfare event</a>
    </div>
    <div class="table-responsive">
    <table class="table table-sm align-middle">
        <thead><tr><th>Event</th><th>Member</th><th>Type</th><th>How</th><th class="text-end">Raised</th><th class="text-end">Fund benefit</th><th class="text-end">Paid out</th><th>Status</th></tr></thead>
        @forelse($events as $e)
            <tr><td><a href="{{ route('welfare.show', $e) }}" class="fw-semibold">{{ $e->title }}</a><br><small class="text-muted">{{ $e->event_no }} · {{ $e->event_date?->format('d M Y') }}</small></td>
                <td>{{ $e->member->full_name }}<br><small class="text-muted">{{ $e->member->member_no }}</small></td>
                <td><i class="bi bi-{{ $e->icon() }}"></i> {{ $e->type_label }}@if($e->relationship)<br><small class="text-muted">{{ \App\Models\WelfareEvent::RELATIONSHIPS[$e->relationship] ?? $e->relationship }}</small>@endif</td>
                <td class="small">{{ ['voluntary' => 'Voluntary', 'levy' => 'Levy', 'both' => 'Levy + voluntary'][$e->mode] ?? $e->mode }}@if($e->levy_amount > 0)<br>KES {{ money($e->levy_amount, 0) }} each @endif</td>
                <td class="text-money">{{ money($e->raised) }}</td><td class="text-money">{{ money($e->fund_contribution) }}</td><td class="text-money">{{ money($e->paid_out) }}</td>
                <td><span class="badge text-bg-{{ $e->badge() }}">{{ $e->status_label }}</span></td></tr>
        @empty<tr><td colspan="8" class="text-muted">No welfare events yet.</td></tr>@endforelse
    </table>
    </div>
    {{ $events->links() }}
</div></div>
@endsection
