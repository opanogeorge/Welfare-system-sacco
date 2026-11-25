@extends('layouts.app')
@section('title', 'Withdrawal Requests')
@section('content')
<div class="card"><div class="card-body">
    <div class="mb-3">@foreach(['pending', 'paid', 'rejected'] as $s)<a href="?status={{ $s }}" class="btn btn-sm {{ request('status', 'pending') === $s ? 'btn-success' : 'btn-outline-secondary' }} me-1">{{ ucfirst($s) }}</a>@endforeach</div>
    <table class="table align-middle">
        <thead><tr><th>Date</th><th>Member</th><th>Account</th><th class="text-end">Amount</th><th>Pay via</th><th>Reason</th><th></th></tr></thead>
        @forelse($rows as $w)
            <tr><td class="small">{{ $w->created_at->format('d/m/Y H:i') }}</td><td><a href="{{ route('members.show', $w->member) }}">{{ $w->member->member_no }}</a> {{ $w->member->full_name }}</td>
                <td>{{ $w->account_code }}</td><td class="text-money fw-semibold">{{ money($w->amount) }}</td><td>{{ $w->channel }} · {{ $w->destination }}</td><td class="small">{{ $w->reason }} {{ $w->remarks }}</td>
                <td class="text-nowrap">@if($w->status === 'pending')
                    <form method="POST" action="{{ route('service.withdrawals.pay', $w) }}" class="d-inline-flex gap-1">@csrf<input name="external_ref" class="form-control form-control-sm" placeholder="M-Pesa/bank ref" style="width:130px"><button class="btn btn-sm btn-success">Pay</button></form>
                    <form method="POST" action="{{ route('service.withdrawals.reject', $w) }}" class="d-inline" onsubmit="const r=prompt('Reason?'); if(!r) return false; this.remarks.value=r;">@csrf<input type="hidden" name="remarks"><button class="btn btn-sm btn-outline-danger">Reject</button></form>
                @else <span class="badge text-bg-light">{{ $w->status }}</span> <small>{{ $w->processor?->name }}</small> @endif</td></tr>
        @empty <tr><td colspan="7" class="text-muted">No requests.</td></tr> @endforelse
    </table>
    {{ $rows->links() }}
</div></div>
@endsection
