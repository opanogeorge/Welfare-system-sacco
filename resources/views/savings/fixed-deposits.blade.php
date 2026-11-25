@extends('layouts.app')
@section('title', 'Fixed Deposits')
@section('content')
<div class="row g-3">
<div class="col-lg-8"><div class="card"><div class="card-body">
    <div class="d-flex gap-3 mb-2 small"><span>Active principal: <strong>KES {{ money($totals['active']) }}</strong></span><span>Interest payable at maturity: <strong>KES {{ money($totals['interest']) }}</strong></span>
        <span class="ms-auto">@foreach(['' => 'Active+all', 'active' => 'Active', 'matured' => 'Matured', 'broken' => 'Broken'] as $k => $v)<a href="?status={{ $k }}" class="me-2">{{ $v }}</a>@endforeach</span></div>
    <table class="table table-sm table-hover align-middle">
        <thead><tr><th>FD No</th><th>Member</th><th class="text-end">Amount</th><th>Rate</th><th>Term</th><th>Maturity</th><th class="text-end">Interest</th><th>Status</th><th></th></tr></thead>
        @forelse($fds as $f)
            <tr><td>{{ $f->fd_no }}</td><td><a href="{{ route('members.show', $f->member) }}">{{ $f->member->member_no }}</a> {{ $f->member->full_name }}</td>
                <td class="text-money">{{ money($f->amount) }}</td><td>{{ $f->rate }}%</td><td>{{ $f->term_months }} mo</td><td>{{ $f->maturity_date->format('d/m/Y') }}</td>
                <td class="text-money">{{ money($f->interest_amount) }}</td><td><span class="badge text-bg-light">{{ $f->status }}</span> @if($f->auto_rollover)<i class="bi bi-arrow-repeat" title="Auto rollover"></i>@endif</td>
                <td>@if($f->status === 'active' && can_route('savings.fd.close'))
                    <form method="POST" action="{{ route('savings.fd.close', $f) }}" data-confirm="{{ $f->maturity_date->isFuture() ? 'Break this deposit early? Reduced interest applies.' : 'Pay out this matured deposit?' }}">@csrf
                        <button class="btn btn-sm btn-outline-{{ $f->maturity_date->isFuture() ? 'danger' : 'success' }}">{{ $f->maturity_date->isFuture() ? 'Break' : 'Pay out' }}</button></form>@endif</td></tr>
        @empty <tr><td colspan="9" class="text-muted">No fixed deposits.</td></tr> @endforelse
    </table>
    {{ $fds->links() }}
</div></div></div>
<div class="col-lg-4"><div class="card"><div class="card-body">
    <h6>Open fixed deposit</h6>
    <form method="POST">@csrf
        <select name="member_id" class="member-select mb-2" required></select>
        <input type="number" step="0.01" name="amount" class="form-control mb-2 mt-2" placeholder="Amount (min {{ money(setting('fd_min_amount', 10000), 0) }})" required>
        <div class="row g-2 mb-2"><div class="col"><input type="number" step="0.01" name="rate" class="form-control" placeholder="Rate % p.a." required></div>
            <div class="col"><select name="term_months" class="form-select">@foreach([3, 6, 9, 12, 18, 24, 36] as $t)<option value="{{ $t }}">{{ $t }} months</option>@endforeach</select></div></div>
        <label class="small mb-2"><input type="checkbox" name="auto_rollover" value="1"> Auto-rollover principal at maturity</label>
        <button class="btn btn-success w-100">Open (funded from member's savings)</button>
    </form>
    <p class="small text-muted mt-2 mb-0">Interest is paid at maturity less {{ setting('fd_wht_rate', 15) }}% WHT. Breaking early pays {{ setting('fd_break_rate_factor', 0.5) * 100 }}% of the rate for the months held.</p>
</div></div></div>
</div>
@endsection
