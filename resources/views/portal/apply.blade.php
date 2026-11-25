@extends('layouts.app')
@section('title', 'Apply for a Loan')
@section('content')
<div class="row g-3 mb-3">
    @foreach($products as $row)
        @php($p = $row['product']) @php($e = $row['eligibility'])
        <div class="col-md-6 col-xl-3"><div class="card h-100 {{ $e['eligible'] ? '' : 'opacity-75' }}"><div class="card-body small">
            <h6>{{ $p->name }}</h6>
            <div>{{ $p->interest_rate }}% p.a. {{ $p->interest_method }} · up to {{ $p->max_period }} months</div>
            <div>Guarantors: {{ $p->min_guarantors ?: 'none' }}</div>
            <div class="mt-2">You qualify for up to</div><div class="fs-5 fw-bold text-success">KES {{ money($e['max_amount'], 0) }}</div>
            @foreach($e['issues'] as $i)<div class="text-danger">• {{ $i }}</div>@endforeach
        </div></div></div>
    @endforeach
</div>

<div class="card"><div class="card-body">
<form method="POST">
    @csrf
    <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Product *</label><select name="loan_product_id" class="form-select" required>
            @foreach($products as $row) @if($row['eligibility']['eligible'])<option value="{{ $row['product']->id }}">{{ $row['product']->name }}</option>@endif @endforeach</select></div>
        <div class="col-md-3"><label class="form-label">Amount (KES) *</label><input type="number" name="amount" value="{{ old('amount') }}" class="form-control" required></div>
        <div class="col-md-2"><label class="form-label">Months *</label><input type="number" name="period" value="{{ old('period', 12) }}" class="form-control" required></div>
        <div class="col-md-3"><label class="form-label">Purpose *</label><input name="purpose" value="{{ old('purpose') }}" class="form-control" required></div>
    </div>
    <h6 class="mt-4">Guarantors</h6>
    <p class="small text-muted">Enter your guarantors' member numbers. They will get an SMS and must accept in their own portal.</p>
    <div id="gList"></div>
    <button type="button" class="btn btn-sm btn-outline-success" onclick="addG()"><i class="bi bi-plus"></i> Add guarantor</button>
    <hr><button class="btn btn-success">Submit application</button>
</form>
</div></div>
@endsection
@push('scripts')
<script>
let i = 0;
function addG() {
    const d = document.createElement('div'); d.className = 'row g-2 mb-2 align-items-center';
    d.innerHTML = `<div class="col-4"><input name="guarantors[${i}][member_no]" class="form-control form-control-sm text-uppercase" placeholder="Member no." required></div>
        <div class="col-3"><input type="number" name="guarantors[${i}][amount]" class="form-control form-control-sm" placeholder="Amount" required></div>
        <div class="col-4 small name text-muted"></div><div class="col-1"><button type="button" class="btn btn-sm btn-link text-danger" onclick="this.closest('.row').remove()"><i class="bi bi-x-lg"></i></button></div>`;
    document.getElementById('gList').appendChild(d);
    const [no, amt] = d.querySelectorAll('input');
    const check = () => no.value && fetch(`{{ route('portal.lookup') }}?member_no=${encodeURIComponent(no.value.toUpperCase())}&amount=${amt.value || 0}`, {headers: {Accept: 'application/json'}})
        .then(r => r.json()).then(j => d.querySelector('.name').innerHTML = !j.name ? '<span class="text-danger">Not found / inactive</span>'
            : (j.eligible === false ? `<span class="text-danger"><i class="bi bi-x-circle"></i> ${j.name}: ${j.message}</span>` : `<i class="bi bi-check-circle text-success"></i> ${j.name}`));
    no.addEventListener('change', check); amt.addEventListener('change', check);
    i++;
}
</script>
@endpush
