@extends('layouts.app')
@section('title', 'Deposit / Pay')
@section('content')
@php
    $targets = collect();
    foreach ($member->accounts as $a) {
        if (in_array($a->type->code, ['DEP', 'SHR', 'SAV'])) $targets->push(['value' => $a->type->code, 'label' => $a->type->name, 'note' => 'Balance KES ' . money($a->balance), 'ref' => $a->type->code === 'DEP' ? $member->member_no : $member->member_no . '-' . $a->type->code]);
    }
    foreach ($loans as $l) {
        $targets->push(['value' => $l->loan_no, 'label' => "Loan {$l->loan_no} · {$l->product->name}", 'note' => 'Outstanding KES ' . money($l->outstanding) . ($l->status === 'disbursed' && $l->arrears() > 0 ? ' · arrears KES ' . money($l->arrears()) : ''), 'ref' => $l->loan_no, 'due' => $l->status === 'disbursed' ? max($l->arrears(), 0) : 0]);
    }
    if ($welfareDue > 0) $targets->push(['value' => 'WLF', 'label' => 'Welfare dues', 'note' => 'Unpaid levies KES ' . money($welfareDue), 'ref' => $member->member_no . '-WLF', 'due' => $welfareDue]);
    foreach ($welfare as $w) {
        $targets->push(['value' => 'W' . $w->id, 'label' => 'Welfare: ' . $w->title, 'note' => $w->type_label . ($w->levy_amount > 0 ? ' · levy KES ' . money($w->levy_amount, 0) : ''), 'ref' => $member->member_no . '-W' . $w->id]);
    }
    $sel = $preselect && $targets->firstWhere('value', $preselect) ? $preselect : 'DEP';
    $paybill = config('sacco.mpesa.paybill');
@endphp
<div class="row g-3">
<div class="col-lg-7">
    <div class="card"><div class="card-body">
        <h5 class="mb-3"><i class="bi bi-phone"></i> Deposit or pay with M-Pesa</h5>
        <form id="payForm">
            <label class="form-label">Pay to</label>
            <div class="list-group mb-3">
                @foreach($targets as $t)
                    <label class="list-group-item d-flex gap-2 align-items-start">
                        <input class="form-check-input mt-1" type="radio" name="to" value="{{ $t['value'] }}" data-ref="{{ $t['ref'] }}" data-due="{{ $t['due'] ?? 0 }}" @checked($sel === $t['value'])>
                        <span><span class="fw-semibold">{{ $t['label'] }}</span><br><small class="text-muted">{{ $t['note'] }}</small></span>
                    </label>
                @endforeach
            </div>
            <div class="row g-2">
                <div class="col-md-6"><label class="form-label">Amount (KES)</label><input type="number" name="amount" id="amount" min="10" max="250000" step="1" class="form-control form-control-lg" required></div>
                <div class="col-md-6"><label class="form-label">M-Pesa phone number</label><input name="phone" value="{{ $member->phone }}" class="form-control form-control-lg" required></div>
            </div>
            @if($stk)
                <button class="btn btn-success btn-lg w-100 mt-3" id="payBtn"><i class="bi bi-send"></i> Send payment request to my phone</button>
            @endif
        </form>
        <div id="payStatus" class="mt-3"></div>
        @unless($stk)
            <div class="alert alert-info mt-3 mb-0">Online payment prompts are not switched on yet. Pay through M-Pesa instead (details on the right).</div>
        @endunless
    </div></div>
</div>
<div class="col-lg-5">
    <div class="card mb-3"><div class="card-body">
        <h6>Or pay via Paybill</h6>
        @if($paybill)
            <ol class="small mb-0">
                <li>M-Pesa → Lipa na M-Pesa → Pay Bill</li>
                <li>Business number: <strong>{{ $paybill }}</strong></li>
                <li>Account number: <strong id="refShow">{{ $member->member_no }}</strong></li>
                <li>Enter the amount and your PIN</li>
            </ol>
            <p class="small text-muted mt-2 mb-0">The account number decides where the money goes. It is posted to your account automatically within a minute.</p>
        @else
            <p class="small text-muted mb-0">Ask the office for the SACCO paybill number.</p>
        @endif
    </div></div>
    <div class="card"><div class="card-body">
        <h6>Recent M-Pesa payments</h6>
        <table class="table table-sm mb-0 small">
            @forelse($recent as $r)
                <tr><td>{{ ($r->trans_time ?? $r->created_at)->format('d/m H:i') }}</td><td>{{ $r->trans_id }}<br><span class="text-muted">{{ $r->bill_ref }}</span></td>
                    <td class="text-money">{{ money($r->amount) }}</td>
                    <td><span class="badge text-bg-{{ ['posted' => 'success', 'unmatched' => 'warning', 'failed' => 'danger'][$r->status] ?? 'secondary' }}">{{ $r->status === 'unmatched' ? 'being checked' : $r->status }}</span></td></tr>
            @empty<tr><td class="text-muted">No M-Pesa payments yet.</td></tr>@endforelse
        </table>
    </div></div>
</div>
</div>
@endsection
@push('scripts')
<script>
(() => {
    const form = document.getElementById('payForm'), status = document.getElementById('payStatus'), refShow = document.getElementById('refShow'), amount = document.getElementById('amount');
    const sync = () => { const r = form.querySelector('input[name=to]:checked'); if (!r) return; if (refShow) refShow.textContent = r.dataset.ref; if (+r.dataset.due && !amount.value) amount.value = Math.ceil(r.dataset.due); };
    form.querySelectorAll('input[name=to]').forEach(r => r.addEventListener('change', () => { amount.value = ''; sync(); }));
    sync();
    const show = (cls, html) => status.innerHTML = `<div class="alert alert-${cls} mb-0">${html}</div>`;
    const btn = document.getElementById('payBtn');
    if (!btn) { form.addEventListener('submit', e => e.preventDefault()); return; }
    form.addEventListener('submit', async e => {
        e.preventDefault();
        btn.disabled = true;
        show('secondary', '<span class="spinner-border spinner-border-sm"></span> Sending request…');
        try {
            const res = await fetch(`{{ route('portal.pay') }}`, {method: 'POST', body: new FormData(form),
                headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}', Accept: 'application/json'}});
            const data = await res.json();
            if (!res.ok) { show('danger', data.message || Object.values(data.errors || {}).flat().join('<br>') || 'Could not send the request.'); btn.disabled = false; return; }
            show('info', `<i class="bi bi-phone-vibrate"></i> ${data.message}<br><small>Waiting for confirmation…</small>`);
            let tries = 0;
            const poll = setInterval(async () => {
                tries++;
                const s = await (await fetch(`{{ url('portal/pay/status') }}/${data.checkout}`, {headers: {Accept: 'application/json'}})).json();
                if (s.status === 'success' || s.status === 'received') {
                    clearInterval(poll);
                    show('success', `<i class="bi bi-check-circle"></i> Payment received: KES ${Number(s.amount).toLocaleString()} · M-Pesa ref <strong>${s.receipt}</strong>. ${s.status === 'success' ? 'Your account has been updated.' : 'It will reflect shortly.'}`);
                    setTimeout(() => location.reload(), 4000);
                } else if (s.status === 'failed') {
                    clearInterval(poll); btn.disabled = false;
                    show('warning', `Payment not completed: ${s.message}. You can try again.`);
                } else if (tries > 24) {
                    clearInterval(poll); btn.disabled = false;
                    show('secondary', 'No confirmation yet. If you entered your PIN, the payment will show under "Recent M-Pesa payments" shortly.');
                }
            }, 5000);
        } catch (err) { show('danger', 'Network error. Please try again.'); btn.disabled = false; }
    });
})();
</script>
@endpush
