@extends('layouts.app')
@section('title', 'Employers & Check-off')
@section('content')
<div class="row g-3">
<div class="col-lg-8"><div class="card"><div class="card-body">
    <table class="table table-hover align-middle">
        <thead><tr><th>Code</th><th>Employer</th><th>Members</th><th>Payroll day</th><th class="text-end">Deductions posted</th><th class="text-end">Remitted</th><th class="text-end">Outstanding</th></tr></thead>
        @forelse($employers as $e)
            @php($o = $outstanding[$e->id] ?? null)
            <tr><td>{{ $e->code }}</td><td><a href="{{ route('employers.show', $e) }}" class="fw-semibold">{{ $e->name }}</a><br><small class="text-muted">{{ $e->contact_person }} {{ $e->phone }}</small></td>
                <td>{{ $e->members_count }}</td><td>{{ $e->payroll_day }}</td>
                <td class="text-money">{{ money($o['posted'] ?? 0) }}</td><td class="text-money">{{ money($o['remitted'] ?? 0) }}</td>
                <td class="text-money {{ ($o['outstanding'] ?? 0) > 0 ? 'text-danger fw-semibold' : '' }}">{{ money($o['outstanding'] ?? 0) }}</td></tr>
        @empty <tr><td colspan="7" class="text-muted">No employers yet.</td></tr> @endforelse
    </table>
</div></div></div>
<div class="col-lg-4"><div class="card"><div class="card-body">
    <h6>Register employer</h6>
    <form method="POST" action="{{ route('employers.store') }}">@csrf
        <div class="row g-2">
            <div class="col-4"><input name="code" class="form-control" placeholder="Code" required></div>
            <div class="col-8"><input name="name" class="form-control" placeholder="Employer name" required></div>
            <div class="col-6"><input name="contact_person" class="form-control" placeholder="HR / payroll contact"></div>
            <div class="col-6"><input name="phone" class="form-control" placeholder="Phone"></div>
            <div class="col-8"><input name="email" type="email" class="form-control" placeholder="Email"></div>
            <div class="col-4"><input name="payroll_day" type="number" value="25" class="form-control" title="Payroll day"></div>
            <div class="col-12"><input name="address" class="form-control" placeholder="Address"></div>
        </div>
        <button class="btn btn-success w-100 mt-2">Save</button>
    </form>
    <p class="small text-muted mt-2 mb-0">Link members to an employer from the member form. Each month: generate the check-off schedule, export it to the employer, then post the returned deductions and record the remittance.</p>
</div></div></div>
</div>
@endsection
