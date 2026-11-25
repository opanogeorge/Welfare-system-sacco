@extends('layouts.app')
@section('title', 'Prudential Ratios')
@section('content')
@include('accounting._nav')
<div class="card col-xl-9"><div class="card-body">
    @include('accounting._period', ['heading' => 'KEY PRUDENTIAL INDICATORS'])
    <table class="table">
        <thead class="table-light"><tr><th>Indicator</th><th class="text-end">Actual</th><th class="text-end">Benchmark</th><th></th></tr></thead>
        @foreach($ratios as [$name, $value, $benchmark])
            @php($ok = str_starts_with($benchmark, '≥') ? $value >= (float) filter_var($benchmark, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : $value <= (float) filter_var($benchmark, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION))
            <tr><td>{{ $name }}</td><td class="text-money fw-semibold">{{ number_format($value, 2) }}%</td><td class="text-money text-muted">{{ $benchmark }}</td>
                <td><i class="bi bi-{{ $ok ? 'check-circle text-success' : 'exclamation-triangle text-danger' }}"></i></td></tr>
        @endforeach
    </table>
    <div class="row small text-muted">
        <div class="col-md-6">Core capital: KES {{ money($core) }}<br>Institutional capital: KES {{ money($institutional) }}<br>Total assets: KES {{ money($totalAssets) }}</div>
        <div class="col-md-6">Member deposits &amp; savings: KES {{ money($deposits) }}<br>Cash &amp; bank: KES {{ money($cash) }}<br>Loans to members: KES {{ money($loans) }}</div>
    </div>
    <p class="small text-muted mt-3 mb-0">Indicative management figures computed from the general ledger using the SASRA Sacco Societies Regulations thresholds. They are not the official SASRA return forms; the regulator's templates still have to be completed from your audited figures.</p>
</div></div>
@endsection
