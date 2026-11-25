@extends('layouts.app')
@section('title', 'AML & Compliance Alerts')
@section('content')
<div class="card mb-3"><div class="card-body">
    <h6>Large cash transactions (≥ KES {{ money(setting('aml_cash_threshold', 1000000), 0) }})</h6>
    <table class="table table-sm">
        <thead><tr><th>When</th><th>Member</th><th>Type</th><th class="text-end">Amount</th><th>Posted by</th></tr></thead>
        @forelse($flags as $f)
            @php($m = $members[$f->data['member_id'] ?? 0] ?? null)
            <tr><td>{{ $f->created_at->format('d/m/Y H:i') }}</td><td>@if($m)<a href="{{ route('members.show', $m) }}">{{ $m->member_no }}</a> {{ $m->full_name }}@endif</td>
                <td>{{ $f->data['type'] ?? '' }}</td><td class="text-money">{{ money($f->data['amount'] ?? 0) }}</td><td>{{ $f->user?->name }}</td></tr>
        @empty <tr><td colspan="5" class="text-muted">No alerts.</td></tr> @endforelse
    </table>
    {{ $flags->links() }}
    <p class="small text-muted mb-0">Review each alert and, where suspicious, file a report with the Financial Reporting Centre (goAML). The system flags; the compliance officer decides.</p>
</div></div>
<div class="card"><div class="card-body">
    <h6>High-risk and politically exposed members (enhanced due diligence)</h6>
    <table class="table table-sm mb-0">
        @forelse($highRisk as $m)<tr><td><a href="{{ route('members.show', $m) }}">{{ $m->member_no }}</a> {{ $m->full_name }}</td><td>{{ $m->risk_rating }}</td><td>{{ $m->is_pep ? 'PEP' : '' }}</td><td class="small">{{ $m->occupation }} · {{ $m->source_of_funds }}</td></tr>
        @empty<tr><td class="text-muted">None flagged.</td></tr>@endforelse
    </table>
</div></div>
@endsection
