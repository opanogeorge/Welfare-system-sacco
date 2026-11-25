@extends('layouts.app')
@section('title', 'Credit Reference Bureau')
@section('content')
<div class="card mb-3"><div class="card-body d-flex flex-wrap gap-3 align-items-center">
    <div><strong>Metropol API:</strong> {!! $configured ? '<span class="text-success">connected</span>' : '<span class="text-muted">not configured – manual entry only</span>' !!}
        @if(can_route('admin.integrations'))<a href="{{ route('admin.integrations', ['tab' => 'crb']) }}" class="small ms-2">settings</a>@endif</div>
    @if(can_route('crb.export') && auth()->user()->isHq())
        <a href="{{ route('crb.export') }}" class="btn btn-outline-success ms-auto"><i class="bi bi-download"></i> Credit information sharing file (CSV)</a>
    @endif
</div></div>
<div class="card"><div class="card-body">
    <p class="small text-muted">Run checks from a member's profile or a loan application (consent is required each time). This is the log of all checks.</p>
    <table class="table table-sm table-hover">
        <thead><tr><th>Date</th><th>Member</th><th>Bureau</th><th>Type</th><th class="text-end">Score</th><th>Listed</th><th>Summary</th><th>By</th><th></th></tr></thead>
        @forelse($checks as $c)
            <tr class="{{ $c->status === 'failed' ? 'text-muted' : '' }}"><td class="small">{{ $c->created_at->format('d/m/Y H:i') }}</td>
                <td><a href="{{ route('members.show', $c->member) }}">{{ $c->member->member_no }}</a> {{ $c->member->full_name }}</td>
                <td>{{ ucfirst($c->provider) }}</td><td>{{ $c->check_type }}</td><td class="text-money">{{ $c->score ?? '—' }}</td>
                <td>@if($c->is_listed === true)<span class="badge text-bg-danger">Negative</span>@elseif($c->is_listed === false)<span class="badge text-bg-success">Clear</span>@endif</td>
                <td class="small">{{ $c->summary }}</td><td class="small">{{ $c->checker?->name }}</td>
                <td>@if($c->report_path)<a href="{{ route('crb.report', $c) }}"><i class="bi bi-file-pdf"></i></a>@endif</td></tr>
        @empty <tr><td colspan="9" class="text-muted">No CRB checks yet.</td></tr> @endforelse
    </table>
    {{ $checks->links() }}
</div></div>
@endsection
