<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Statement {{ $member->member_no }}</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #222; }
    h2 { margin: 0; font-size: 15px; } .muted { color: #666; }
    table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    th { background: {{ theme()['brand'] }}; color: #fff; text-align: left; padding: 4px; font-size: 9px; }
    td { padding: 3px 4px; border-bottom: 1px solid #ddd; } .r { text-align: right; }
    .box { display: inline-block; width: 23%; border: 1px solid #ccc; padding: 5px; margin-right: 1%; }
</style></head>
<body>
<table style="margin:0"><tr>
    <td style="border:0"><h2>{{ sacco_name() }}</h2><div class="muted">{{ setting('sacco_address') }} · {{ setting('sacco_phone') }} · {{ setting('sacco_email') }}</div>
        <strong>MEMBER STATEMENT: {{ $from->format('d M Y') }} to {{ $to->format('d M Y') }}</strong></td>
    <td style="border:0" class="r"><strong>{{ $member->full_name }}</strong><br>Member No: {{ $member->member_no }}<br>ID: {{ $member->national_id }}<br>Generated: {{ now()->format('d/m/Y H:i') }}</td>
</tr></table>
<div style="margin-top:8px">
    @foreach($member->accounts as $a)<div class="box"><span class="muted">{{ $a->type->name }}</span><br><strong>KES {{ money($a->balance) }}</strong></div>@endforeach
    <div class="box"><span class="muted">Loans outstanding</span><br><strong>KES {{ money($member->outstandingLoans()) }}</strong></div>
</div>
<table>
    <thead><tr><th>Date</th><th>Reference</th><th>Account</th><th>Description</th><th class="r">Debit</th><th class="r">Credit</th><th class="r">Balance</th></tr></thead>
    @forelse($rows as $t)
        <tr><td>{{ $t->txn_date->format('d/m/Y') }}</td><td>{{ $t->reference }}</td><td>{{ $t->account?->type->code ?? $t->loan?->loan_no }}</td>
            <td>{{ $t->description ?? str_replace('_', ' ', $t->type) }}</td>
            <td class="r">{{ $t->direction === 'debit' ? money($t->amount) : '' }}</td><td class="r">{{ $t->direction === 'credit' ? money($t->amount) : '' }}</td>
            <td class="r">{{ $t->balance_after !== null ? money($t->balance_after) : '' }}</td></tr>
    @empty <tr><td colspan="7">No transactions in this period.</td></tr> @endforelse
</table>
@php($running = $member->loans->where('status', 'disbursed'))
@if($running->count())
<table><thead><tr><th>Loan</th><th>Product</th><th class="r">Principal</th><th class="r">Paid</th><th class="r">Outstanding</th></tr></thead>
    @foreach($running as $l)<tr><td>{{ $l->loan_no }}</td><td>{{ $l->product->name }}</td><td class="r">{{ money($l->amount_approved) }}</td><td class="r">{{ money($l->total_paid) }}</td><td class="r">{{ money($l->outstanding) }}</td></tr>@endforeach
</table>
@endif
<p class="muted" style="margin-top:12px">This is a computer-generated statement. Report any discrepancy to the SACCO office within 30 days.</p>
</body></html>
