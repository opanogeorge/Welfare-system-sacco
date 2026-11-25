@extends('layouts.app')
@section('title', 'Loans')
@section('content')
<div class="card"><div class="card-body">
    <form class="row g-2 mb-3">
        <div class="col-md-4"><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Loan no, member no, name"></div>
        <div class="col-md-3"><select name="status" class="form-select"><option value="">All statuses</option>
            @foreach(\App\Models\Loan::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach</select></div>
        <div class="col-md-3"><select name="product" class="form-select"><option value="">All products</option>
            @foreach($products as $p)<option value="{{ $p->id }}" @selected(request('product') == $p->id)>{{ $p->name }}</option>@endforeach</select></div>
        <div class="col-md-2 d-flex gap-2"><button class="btn btn-outline-secondary"><i class="bi bi-search"></i></button>
            @if(can_route('loans.create'))<a href="{{ route('loans.create') }}" class="btn btn-success flex-grow-1"><i class="bi bi-plus"></i> New</a>@endif</div>
    </form>
    <div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead><tr><th>Loan No</th><th>Member</th><th>Product</th><th>Applied</th><th class="text-end">Amount</th><th>Period</th><th class="text-end">Outstanding</th><th>Status</th></tr></thead>
        @forelse($loans as $l)
            <tr>
                <td><a href="{{ route('loans.show', $l) }}" class="fw-semibold">{{ $l->loan_no }}</a></td>
                <td>{{ $l->member->member_no }} · {{ $l->member->full_name }}</td><td>{{ $l->product->name }}</td>
                <td>{{ $l->applied_on->format('d/m/Y') }}</td>
                <td class="text-money">{{ money($l->amount_approved ?? $l->amount_applied) }}</td><td>{{ $l->period }} mo</td>
                <td class="text-money">{{ $l->status === 'disbursed' ? money($l->outstanding) : '—' }}</td>
                <td><span class="badge text-bg-{{ $l->badge() }}">{{ $l->status_label }}</span></td>
            </tr>
        @empty <tr><td colspan="8" class="text-muted text-center py-4">No loans found.</td></tr> @endforelse
    </table>
    </div>
    {{ $loans->links() }}
</div></div>
@endsection
