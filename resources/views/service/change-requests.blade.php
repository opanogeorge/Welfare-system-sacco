@extends('layouts.app')
@section('title', 'Profile Update Requests')
@section('content')
<div class="card"><div class="card-body">
    <table class="table align-middle">
        <thead><tr><th>Date</th><th>Member</th><th>Requested changes</th><th></th></tr></thead>
        @forelse($rows as $c)
            <tr><td class="small">{{ $c->created_at->format('d/m/Y') }}</td><td><a href="{{ route('members.show', $c->member) }}">{{ $c->member->member_no }}</a> {{ $c->member->full_name }}</td>
                <td class="small">@foreach($c->changes as $k => $v)<div><strong>{{ str_replace('_', ' ', $k) }}:</strong> <span class="text-muted text-decoration-line-through">{{ $c->member->$k }}</span> → {{ $v }}</div>@endforeach</td>
                <td class="text-nowrap">
                    <form method="POST" action="{{ route('service.changes.process', $c) }}" class="d-inline">@csrf<input type="hidden" name="approve" value="1"><button class="btn btn-sm btn-success">Approve</button></form>
                    <form method="POST" action="{{ route('service.changes.process', $c) }}" class="d-inline">@csrf<input type="hidden" name="approve" value="0"><button class="btn btn-sm btn-outline-danger">Reject</button></form>
                </td></tr>
        @empty <tr><td colspan="4" class="text-muted">No pending requests.</td></tr> @endforelse
    </table>
</div></div>
@endsection
