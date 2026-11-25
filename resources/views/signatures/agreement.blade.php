<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Loan agreement {{ $loan->loan_no }}</title>
<style>
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #222; max-width: 800px; margin: 20px auto; padding: 0 16px; }
    h1 { font-size: 17px; margin: 0; } h2 { font-size: 13px; margin: 16px 0 6px; border-bottom: 1px solid #ccc; padding-bottom: 3px; }
    table { width: 100%; border-collapse: collapse; } td, th { padding: 4px 6px; border: 1px solid #ddd; text-align: left; vertical-align: top; }
    .muted { color: #666; } .sig { border: 1px solid #ccc; padding: 6px; width: 48%; display: inline-block; vertical-align: top; margin: 0 1% 8px 0; }
    .sig img { height: 70px; } ol li { margin-bottom: 4px; } .noprint { margin-bottom: 12px; }
    @media print { .noprint { display: none; } }
</style></head>
<body>
@unless($pdf)<div class="noprint"><button onclick="print()">Print</button> <a href="?pdf=1">Download PDF</a></div>@endunless
<table style="border:0"><tr><td style="border:0"><h1>{{ sacco_name() }}</h1><div class="muted">{{ setting('sacco_address') }} · {{ setting('sacco_phone') }}</div></td>
    <td style="border:0;text-align:right"><strong>LOAN AGREEMENT</strong><br>{{ $loan->loan_no }}<br>{{ now()->format('d M Y') }}</td></tr></table>

<h2>1. Parties</h2>
<p>This agreement is between <strong>{{ sacco_name() }}</strong> ("the SACCO") and <strong>{{ $loan->member->full_name }}</strong>, member no. {{ $loan->member->member_no }}, ID {{ $loan->member->national_id }}, phone {{ $loan->member->phone }} ("the Borrower").</p>

<h2>2. Loan terms</h2>
<table>
    <tr><th>Product</th><td>{{ $loan->product->name }}</td><th>Principal</th><td>KES {{ money($loan->amount_approved ?? $loan->amount_applied) }}</td></tr>
    <tr><th>Interest</th><td>{{ $loan->interest_rate }}% p.a. ({{ $loan->interest_method === 'flat' ? 'flat rate' : 'reducing balance' }})</td><th>Period</th><td>{{ $loan->period }} months</td></tr>
    <tr><th>Monthly installment</th><td>KES {{ money($calc['installment']) }}</td><th>Total repayable</th><td>KES {{ money($calc['total_payable']) }}</td></tr>
    <tr><th>Processing fee</th><td>{{ $loan->product->processing_fee_rate }}% of principal</td><th>Insurance</th><td>{{ $loan->product->insurance_rate }}% of principal</td></tr>
    <tr><th>Late payment penalty</th><td>{{ $loan->product->penalty_rate }}% of the amount overdue per month</td><th>Purpose</th><td>{{ $loan->purpose }}</td></tr>
</table>

<h2>3. Conditions</h2>
<ol>
    <li>The Borrower shall repay by monthly installments, by payroll check-off where applicable, or via M-Pesa Paybill {{ config('sacco.mpesa.paybill') }} account {{ $loan->loan_no }}.</li>
    <li>Repayments are applied first to penalties, then interest, then principal.</li>
    <li>If an installment remains unpaid for 90 days, the SACCO may recover the balance from the Borrower's deposits and then from the guarantors' deposits in proportion to their guarantees, and may list the Borrower with licensed credit reference bureaus.</li>
    <li>The Borrower authorises the SACCO to deduct installments from salary through the employer and from their savings account if check-off fails.</li>
    <li>Deposits pledged as security may not be withdrawn while the loan is outstanding.</li>
    <li>This agreement is governed by the SACCO by-laws, the Sacco Societies Act and the laws of Kenya.</li>
</ol>

<h2>4. Guarantors</h2>
<table><tr><th>Guarantor</th><th>Member no.</th><th>Amount guaranteed</th><th>Status</th></tr>
    @forelse($loan->guarantors as $g)<tr><td>{{ $g->member->full_name }}</td><td>{{ $g->member->member_no }}</td><td>KES {{ money($g->amount) }}</td><td>{{ $g->status }}</td></tr>
    @empty<tr><td colspan="4" class="muted">None</td></tr>@endforelse
</table>

<h2>5. Signatures</h2>
@forelse($signatures->concat($guarantees) as $s)
    <div class="sig">
        @if($s['img'])<img src="{{ $s['img'] }}" alt="signature"><br>@endif
        <strong>{{ $s['sig']->signer_name }}</strong> – {{ $s['sig']->signer_role }}<br>
        <span class="muted">Signed {{ $s['sig']->signed_at->format('d M Y H:i') }} · IP {{ $s['sig']->ip }}<br>
        Verify: {{ url('/verify/' . $s['sig']->verification_code) }}</span>
    </div>
@empty
    <p class="muted">Not yet signed.</p>
@endforelse
<p class="muted" style="margin-top:14px">Electronically signed with an audit trail. Each signature's verification code confirms who signed, when, and that these terms have not changed since.</p>
</body></html>
