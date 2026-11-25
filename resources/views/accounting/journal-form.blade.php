@extends('layouts.app')
@section('title', 'Manual Journal')
@section('content')
@include('accounting._nav')
<div class="card"><div class="card-body">
<form method="POST" action="{{ route('accounting.journals.store') }}">@csrf
    <div class="row g-2 mb-3">
        <div class="col-md-3"><label class="form-label small">Date</label><input type="date" name="entry_date" value="{{ old('entry_date', today()->toDateString()) }}" class="form-control" required></div>
        <div class="col-md-9"><label class="form-label small">Narration</label><input name="description" value="{{ old('description') }}" class="form-control" placeholder="e.g. Bank charges for November, Provision for loan losses" required></div>
    </div>
    <table class="table table-sm" id="jl">
        <thead><tr><th>Account</th><th style="width:150px">Debit</th><th style="width:150px">Credit</th><th>Memo</th><th></th></tr></thead>
        <tbody></tbody>
        <tfoot><tr class="fw-bold"><td class="text-end">Totals</td><td id="tdr" class="text-money">0.00</td><td id="tcr" class="text-money">0.00</td><td colspan="2" id="diff"></td></tr></tfoot>
    </table>
    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addLine()"><i class="bi bi-plus"></i> Line</button>
    <hr><button class="btn btn-success">Save as draft</button>
    <span class="small text-muted ms-2">Maker-checker: a different user must post the journal before it affects the books.</span>
</form>
</div></div>
@endsection
@push('scripts')
<script>
const opts = `@foreach($accounts as $a)<option value="{{ $a->id }}">{{ $a->code }} · {{ $a->name }}</option>@endforeach`;
let n = 0;
function addLine() {
    const tr = document.createElement('tr');
    tr.innerHTML = `<td><select name="lines[${n}][gl_account_id]" class="form-select form-select-sm" required><option value=""></option>${opts}</select></td>
      <td><input type="number" step="0.01" min="0" name="lines[${n}][debit]" class="form-control form-control-sm amt"></td>
      <td><input type="number" step="0.01" min="0" name="lines[${n}][credit]" class="form-control form-control-sm amt"></td>
      <td><input name="lines[${n}][memo]" class="form-control form-control-sm"></td>
      <td><button type="button" class="btn btn-sm btn-link text-danger" onclick="this.closest('tr').remove();tot()"><i class="bi bi-x"></i></button></td>`;
    document.querySelector('#jl tbody').appendChild(tr); n++;
}
function tot() {
    let d = 0, c = 0;
    document.querySelectorAll('input[name$="[debit]"]').forEach(i => d += +i.value || 0);
    document.querySelectorAll('input[name$="[credit]"]').forEach(i => c += +i.value || 0);
    tdr.textContent = d.toFixed(2); tcr.textContent = c.toFixed(2);
    diff.innerHTML = Math.abs(d - c) < 0.005 ? '<span class="text-success">Balanced</span>' : `<span class="text-danger">Out by ${(d - c).toFixed(2)}</span>`;
}
document.addEventListener('input', e => e.target.classList.contains('amt') && tot());
addLine(); addLine();
</script>
@endpush
