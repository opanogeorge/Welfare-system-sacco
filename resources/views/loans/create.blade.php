@extends('layouts.app')
@section('title', 'New Loan Application')
@section('content')
<form method="POST" action="{{ route('loans.store') }}" id="loanForm">
@csrf
<div class="row g-3">
<div class="col-lg-7">
    @if($topUp)
        <input type="hidden" name="top_up_of" id="top_up_of" value="{{ $topUp->id }}">
        <div class="alert alert-info">Top-up of <strong>{{ $topUp->loan_no }}</strong> ({{ $topUp->product->name }}). Current balance KES {{ money($topUp->outstanding) }} will be cleared from the new loan at disbursement.
            <div class="form-check mt-2"><input type="checkbox" name="refinance" value="1" class="form-check-input" id="refi" @checked(old('refinance'))><label class="form-check-label" for="refi">Refinance (record as a refinancing, e.g. new rate/product, rather than a plain top-up)</label></div></div>
    @endif
    <div class="card mb-3"><div class="card-body">
        <div class="row g-3">
            <div class="col-12"><label class="form-label">Member *</label>
                <select name="member_id" id="member_id" required>@if($member)<option value="{{ $member->id }}" selected>{{ $member->member_no }} - {{ $member->full_name }}</option>@endif</select></div>
            <div class="col-md-6"><label class="form-label">Loan product *</label>
                <select name="loan_product_id" id="product" class="form-select" required>
                    <option value="">Select…</option>
                    @foreach($products as $p)<option value="{{ $p->id }}" data-rate="{{ $p->interest_rate }}" data-method="{{ $p->interest_method }}" @selected(old('loan_product_id') == $p->id)>{{ $p->name }} ({{ $p->interest_rate }}% {{ $p->interest_method }})</option>@endforeach
                </select></div>
            <div class="col-md-3"><label class="form-label">Amount *</label><input type="number" step="0.01" name="amount" id="amount" value="{{ old('amount') }}" class="form-control" required></div>
            <div class="col-md-3"><label class="form-label">Period (months) *</label><input type="number" name="period" id="period" value="{{ old('period', 12) }}" class="form-control" required></div>
            <div class="col-12"><label class="form-label">Purpose</label><input name="purpose" value="{{ old('purpose') }}" class="form-control"></div>
        </div>
    </div></div>

    <div class="card"><div class="card-body">
        <div class="d-flex mb-2"><h6 class="mb-0">Guarantors</h6><span class="ms-auto small text-muted" id="gNeed"></span></div>
        <table class="table table-sm" id="gTable"><thead><tr><th>Member</th><th style="width:160px">Amount</th><th></th></tr></thead><tbody></tbody></table>
        <button type="button" class="btn btn-sm btn-outline-success" onclick="addGuarantor()"><i class="bi bi-plus"></i> Add guarantor</button>
    </div></div>
</div>
<div class="col-lg-5">
    <div class="card mb-3"><div class="card-body" id="elig"><h6>Eligibility</h6><p class="text-muted small mb-0">Pick a member and product.</p></div></div>
    <div class="card mb-3"><div class="card-body"><h6>Estimate</h6><div id="estimate" class="small text-muted">—</div></div></div>
    <button class="btn btn-success btn-lg w-100"><i class="bi bi-send"></i> Submit application</button>
</div>
</div>
</form>
@endsection
@push('scripts')
<script>
let gi = 0;
function addGuarantor() {
    const tr = document.createElement('tr');
    tr.innerHTML = `<td><select name="guarantors[${gi}][member_id]" required></select><small class="text-muted cap"></small></td>
        <td><input type="number" step="0.01" name="guarantors[${gi}][amount]" class="form-control form-control-sm" required></td>
        <td><button type="button" class="btn btn-sm btn-link text-danger" onclick="this.closest('tr').remove()"><i class="bi bi-x-lg"></i></button></td>`;
    document.querySelector('#gTable tbody').appendChild(tr);
    const ts = memberPicker(tr.querySelector('select'), id => {
        const o = ts.options[id]; if (o) tr.querySelector('.cap').textContent = 'Can guarantee up to KES ' + Number(o.free_guarantee).toLocaleString();
    });
    gi++;
}
const member = memberPicker(document.getElementById('member_id'), check);
['product', 'amount', 'period'].forEach(id => document.getElementById(id).addEventListener('change', () => { check(); estimate(); }));

function check() {
    const m = document.getElementById('member_id').value, p = document.getElementById('product').value;
    if (!m || !p) return;
    const tu = document.getElementById('top_up_of');
    fetch(`{{ route('loans.eligibility') }}?member_id=${m}&product_id=${p}${tu ? '&top_up_of=' + tu.value : ''}`).then(r => r.json()).then(e => {
        document.getElementById('elig').innerHTML = `<h6>Eligibility</h6>
          <table class="table table-sm small mb-2">
            <tr><td>Deposits</td><td class="text-end">${fmt(e.deposits)}</td></tr>
            <tr><td>Running loans</td><td class="text-end">${fmt(e.outstanding)}</td></tr>
            <tr><td><strong>Maximum loan</strong></td><td class="text-end"><strong>${fmt(e.max_amount)}</strong></td></tr>
            <tr><td>Period</td><td class="text-end">${e.min_period}–${e.max_period} months</td></tr></table>
          ${e.eligible ? '<span class="badge text-bg-success">Eligible</span>' : e.issues.map(i => `<div class="text-danger small">• ${i}</div>`).join('')}`;
        document.getElementById('gNeed').textContent = e.min_guarantors ? `Minimum ${e.min_guarantors} required` : 'Not required';
        while (document.querySelectorAll('#gTable tbody tr').length < e.min_guarantors) addGuarantor();
    });
}
function estimate() {
    const o = document.getElementById('product').selectedOptions[0];
    const P = +document.getElementById('amount').value, n = +document.getElementById('period').value;
    if (!o || !o.dataset.rate || !P || !n) return;
    const r = +o.dataset.rate, monthly = o.dataset.method === 'flat'
        ? (P + P * r / 100 * n / 12) / n
        : o.dataset.method === 'equal_principal' ? P / n + P * r / 1200
        : (r ? P * (r / 1200) / (1 - Math.pow(1 + r / 1200, -n)) : P / n);
    document.getElementById('estimate').innerHTML = `${o.dataset.method === 'equal_principal' ? 'First installment (then declining)' : 'Monthly installment'} ≈ <strong class="text-dark">KES ${fmt(monthly)}</strong><br>Total repayable ≈ KES ${fmt(monthly * n)}`;
}
const fmt = v => Number(v).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
check(); estimate();
</script>
@endpush
