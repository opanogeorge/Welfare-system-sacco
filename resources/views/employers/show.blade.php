@extends('layouts.app')
@section('title', $employer->name)
@section('content')
<div class="row g-3">
<div class="col-lg-8">
    <div class="card mb-3"><div class="card-body">
        <div class="d-flex mb-2"><h6 class="mb-0">Check-off schedules</h6>
            <form method="POST" action="{{ route('employers.generate', $employer) }}" class="ms-auto d-flex gap-1">@csrf
                <input type="month" name="period" value="{{ now()->format('Y-m') }}" class="form-control form-control-sm"><button class="btn btn-sm btn-success text-nowrap">Generate schedule</button></form>
        </div>
        <table class="table table-sm table-hover">
            <thead><tr><th>Period</th><th class="text-end">Expected</th><th class="text-end">Posted</th><th class="text-end">Remitted</th><th class="text-end">Variance</th><th>Status</th></tr></thead>
            @forelse($schedules as $s)
                <tr><td><a href="{{ route('checkoff.schedules.show', $s) }}">{{ $s->period }}</a></td><td class="text-money">{{ money($s->expected_total) }}</td>
                    <td class="text-money">{{ money($s->received_total) }}</td><td class="text-money">{{ money($s->remittances_sum_amount) }}</td>
                    <td class="text-money {{ abs($s->expected_total - $s->received_total) > 0.01 ? 'text-danger' : '' }}">{{ money($s->received_total - $s->expected_total) }}</td>
                    <td><span class="badge text-bg-light">{{ $s->status }}</span></td></tr>
            @empty <tr><td colspan="6" class="text-muted">No schedules yet.</td></tr> @endforelse
        </table>
    </div></div>
    <div class="card"><div class="card-body">
        <h6>Members ({{ $members->count() }})</h6>
        <table class="table table-sm">@foreach($members as $m)<tr><td><a href="{{ route('members.show', $m) }}">{{ $m->member_no }}</a></td><td>{{ $m->full_name }}</td><td>{{ $m->payroll_no }}</td><td class="text-money">{{ money($m->monthly_contribution) }}</td></tr>@endforeach</table>
    </div></div>
</div>
<div class="col-lg-4">
    @if(can_route('employers.remit'))
    <div class="card mb-3"><div class="card-body">
        <h6>Record remittance received</h6>
        <form method="POST" action="{{ route('employers.remit', $employer) }}">@csrf
            <input type="number" step="0.01" name="amount" class="form-control mb-2" placeholder="Amount" required>
            <input type="date" name="received_on" value="{{ today()->toDateString() }}" class="form-control mb-2" required>
            <select name="bank_account_id" class="form-select mb-2">@foreach($banks as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select>
            <select name="checkoff_schedule_id" class="form-select mb-2"><option value="">Schedule (optional)</option>@foreach($schedules as $s)<option value="{{ $s->id }}">{{ $s->period }}</option>@endforeach</select>
            <input name="reference" class="form-control mb-2" placeholder="Bank reference">
            <button class="btn btn-success w-100">Record</button>
        </form>
    </div></div>
    @endif
    <div class="card mb-3"><div class="card-body small">
        <h6>Recent remittances</h6>
        <table class="table table-sm mb-0">@forelse($remittances as $r)<tr><td>{{ $r->received_on->format('d/m/Y') }}</td><td>{{ $r->reference }}</td><td class="text-money">{{ money($r->amount) }}</td></tr>@empty<tr><td class="text-muted">None</td></tr>@endforelse</table>
    </div></div>
    <div class="card"><div class="card-body">
        <h6>Edit employer</h6>
        <form method="POST" action="{{ route('employers.update', $employer) }}">@csrf @method('PUT')
            @foreach(['code', 'name', 'contact_person', 'phone', 'email', 'address', 'payroll_day'] as $f)
                <input name="{{ $f }}" value="{{ $employer->$f }}" class="form-control form-control-sm mb-1" placeholder="{{ $f }}">
            @endforeach
            <label class="small"><input type="checkbox" name="is_active" value="1" @checked($employer->is_active)> Active</label>
            <button class="btn btn-sm btn-outline-secondary w-100 mt-1">Update</button>
        </form>
    </div></div>
</div>
</div>
@endsection
