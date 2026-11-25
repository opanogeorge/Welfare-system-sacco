<form method="POST" action="{{ route('portal.loans.guarantors.add', $loan) }}" class="border rounded p-2 g-lookup">@csrf
    @if($replace)<input type="hidden" name="replace" value="{{ $replace }}">@endif
    <div class="row g-1">
        <div class="col-5"><input name="member_no" class="form-control form-control-sm text-uppercase" placeholder="Member no." required></div>
        <div class="col-4"><input type="number" name="amount" value="{{ $amount }}" class="form-control form-control-sm" placeholder="Amount" required></div>
        <div class="col-3"><button class="btn btn-sm btn-primary w-100">Send request</button></div>
    </div>
    <div class="small mt-1 g-msg text-muted">They'll get an SMS and must accept in their portal.</div>
</form>
@once
@push('scripts')
<script>
document.querySelectorAll('form.g-lookup').forEach(f => {
    const no = f.querySelector('[name=member_no]'), amt = f.querySelector('[name=amount]'), msg = f.querySelector('.g-msg');
    const run = () => { if (!no.value) return;
        fetch(`{{ route('portal.lookup') }}?member_no=${encodeURIComponent(no.value.toUpperCase())}&amount=${amt.value || 0}`, {headers: {Accept: 'application/json'}})
            .then(r => r.json()).then(d => {
                if (!d.name) { msg.className = 'small mt-1 g-msg text-danger'; msg.textContent = 'No active member with that number.'; return; }
                msg.className = 'small mt-1 g-msg ' + (d.eligible === false ? 'text-danger' : 'text-success');
                msg.textContent = d.name + (d.message ? ' · ' + d.message : '');
            }); };
    no.addEventListener('change', run); amt.addEventListener('change', run);
});
</script>
@endpush
@endonce
