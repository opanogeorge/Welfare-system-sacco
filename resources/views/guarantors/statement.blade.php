@extends('layouts.app')
@section('title', 'Guarantor Liability Statement · ' . $member->member_no)
@section('content')
<div class="no-print mb-3"><button onclick="print()" class="btn btn-primary"><i class="bi bi-printer"></i> Print / Save PDF</button>
    <a href="{{ route('guarantors.show', $member) }}" class="btn btn-link">Back</a></div>
<div class="card"><div class="card-body p-4" style="max-width: 900px; margin: auto">
    <div class="text-center border-bottom pb-2 mb-3"><h5 class="mb-0">{{ sacco_name() }}</h5><div class="fw-semibold">GUARANTOR LIABILITY STATEMENT</div>
        <div class="small text-muted">As at {{ now()->format('d F Y H:i') }}</div></div>
    <div class="row small mb-3">
        <div class="col-6"><strong>{{ $member->full_name }}</strong><br>Member no. {{ $member->member_no }}<br>{{ $member->phone }}</div>
        <div class="col-6 text-end">Loanable deposits: KES {{ money($p['deposits']) }}<br>Guarantee limit: KES {{ money($p['limit']) }}<br>Free capacity: KES {{ money($p['capacity']) }}</div>
    </div>
    <table class="table table-sm table-bordered small">
        <thead class="table-light"><tr><th>Loan</th><th>Borrower</th><th>Disbursed</th><th class="text-end">Loan amount</th><th class="text-end">Principal balance</th><th class="text-end">Guaranteed</th><th class="text-end">Current liability</th><th>Borrower status</th></tr></thead>
        @forelse($p['active'] as $g)
            @php($l = $g->loan)
            <tr><td>{{ $l->loan_no }}</td><td>{{ $l->member->full_name }} ({{ $l->member->member_no }})</td><td>{{ $l->disbursed_on?->format('d/m/Y') ?? 'not yet' }}</td>
                <td class="text-end">{{ money($l->amount_approved ?: $l->amount_applied) }}</td><td class="text-end">{{ money($l->principal_balance) }}</td>
                <td class="text-end">{{ money($g->amount) }}</td><td class="text-end fw-bold">{{ money($g->exposure()) }}</td>
                <td>{{ $l->status === 'disbursed' ? ($l->daysInArrears() ? $l->daysInArrears() . ' days in arrears' : 'Up to date') : str_replace('_', ' ', $l->status) }}</td></tr>
        @empty<tr><td colspan="8">No active guarantees.</td></tr>@endforelse
        <tfoot class="fw-bold"><tr><td colspan="5">Total</td><td class="text-end">{{ money($p['guaranteed']) }}</td><td class="text-end">{{ money($p['exposure']) }}</td><td></td></tr></tfoot>
    </table>
    @php($past = $p['rows']->whereIn('status', ['released', 'withdrawn'])->take(15))
    @if($past->count() || $p['recovered'] > 0)
        <h6 class="mt-3">Released guarantees and recoveries</h6>
        <table class="table table-sm small">@foreach($p['rows']->filter(fn ($g) => in_array($g->status, ['released', 'withdrawn']) || $g->recovered_amount > 0)->take(20) as $g)
            <tr><td>{{ $g->loan->loan_no }}</td><td>{{ $g->loan->member->full_name }}</td><td class="text-end">{{ money($g->amount) }}</td><td>{{ $g->status_label }} {{ $g->released_at?->format('d/m/Y') }}</td>
                <td>{{ $g->release_reason }}</td><td class="text-end text-danger">{{ $g->recovered_amount > 0 ? 'Recovered ' . money($g->recovered_amount) : '' }}</td></tr>@endforeach</table>
    @endif
    <p class="small text-muted mt-3 mb-0">Current liability = amount guaranteed × (principal balance ÷ amount disbursed). It reduces as the borrower repays and ends when the loan is cleared. If a borrower defaults, the SACCO may recover up to the current liability from the guarantor's deposits in line with the by-laws.</p>
</div></div>
@endsection
