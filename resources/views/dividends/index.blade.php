@extends('layouts.app')
@section('title', 'Dividends & Interest on Deposits')
@section('content')
<div class="row g-3">
<div class="col-lg-8">
    <div class="card"><div class="card-body">
        <table class="table table-hover align-middle">
            <thead><tr><th>Year</th><th>Dividend on shares</th><th>Interest on deposits</th><th>WHT</th><th class="text-end">Gross</th><th class="text-end">Net</th><th>Status</th></tr></thead>
            @forelse($periods as $p)
                <tr><td><a href="{{ route('dividends.show', $p) }}" class="fw-semibold">{{ $p->year }}</a></td><td>{{ $p->dividend_rate }}%</td><td>{{ $p->interest_rate }}%</td><td>{{ $p->withholding_tax_rate }}%</td>
                    <td class="text-money">{{ money($p->total_gross) }}</td><td class="text-money">{{ money($p->total_net) }}</td>
                    <td><span class="badge text-bg-{{ ['paid' => 'success', 'approved' => 'primary', 'computed' => 'info'][$p->status] ?? 'secondary' }}">{{ $p->status }}</span></td></tr>
            @empty <tr><td colspan="7" class="text-muted">No dividend periods yet.</td></tr> @endforelse
        </table>
    </div></div>
</div>
<div class="col-lg-4">
    <div class="card"><div class="card-body">
        <h6>New period</h6>
        <form method="POST" action="{{ route('dividends.store') }}">@csrf
            <div class="mb-2"><label class="form-label small">Financial year</label><input type="number" name="year" value="{{ now()->year - 1 }}" class="form-control" required></div>
            <div class="mb-2"><label class="form-label small">Dividend rate on share capital (%)</label><input type="number" step="0.01" name="dividend_rate" class="form-control" required></div>
            <div class="mb-2"><label class="form-label small">Interest rate on deposits (%)</label><input type="number" step="0.01" name="interest_rate" class="form-control" required></div>
            <div class="mb-2"><label class="form-label small">Withholding tax (%)</label><input type="number" step="0.01" name="withholding_tax_rate" value="5" class="form-control" required></div>
            <div class="mb-3"><label class="form-label small">Pay into</label><select name="payout_destination" class="form-select"><option value="savings">Savings account (FOSA)</option><option value="share_capital">Share capital (capitalise)</option></select></div>
            <button class="btn btn-success w-100">Create</button>
        </form>
        <p class="small text-muted mt-2 mb-0">Rates as declared at the AGM. Interest on deposits uses the average of the 12 month-end balances.</p>
    </div></div>
</div>
</div>
@endsection
