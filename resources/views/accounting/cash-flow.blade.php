@extends('layouts.app')
@section('title', 'Statement of Cash Flows')
@section('content')
@include('accounting._nav')
<div class="card col-xl-8"><div class="card-body">
    @include('accounting._period', ['heading' => 'STATEMENT OF CASH FLOWS (DIRECT METHOD)'])
    <table class="table table-sm">
        @foreach($cf['groups'] as $group => $items)
            <tr class="table-light"><th colspan="2">{{ strtoupper($group) }} ACTIVITIES</th></tr>
            @forelse($items as $name => $amt)<tr><td class="ps-3">{{ $name }}</td><td class="text-money {{ $amt < 0 ? 'text-danger' : '' }}">{{ money($amt) }}</td></tr>
            @empty<tr><td class="ps-3 text-muted" colspan="2">None</td></tr>@endforelse
            <tr class="fw-bold"><td>Net cash from {{ strtolower($group) }} activities</td><td class="text-money">{{ money(array_sum($items)) }}</td></tr>
        @endforeach
        <tr class="fw-bold border-top"><td>Net increase / (decrease) in cash</td><td class="text-money">{{ money($cf['net']) }}</td></tr>
        <tr><td>Cash &amp; equivalents at start</td><td class="text-money">{{ money($cf['opening']) }}</td></tr>
        <tr class="fw-bold table-success"><td>Cash &amp; equivalents at end</td><td class="text-money">{{ money($cf['closing']) }}</td></tr>
    </table>
    <p class="small text-muted mb-0">Cash = accounts flagged “cash” in the chart of accounts. Member deposits, savings and share capital are classified as financing; asset purchases and disposals as investing; everything else (loans, interest, fees, expenses) as operating.</p>
</div></div>
@endsection
