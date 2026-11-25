@extends('layouts.app')
@section('title', 'Reports')
@section('content')
@php
$groups = [
    'Members & savings' => [
        ['reports.register', 'people', 'Member Register', 'All members with KYC status, gender, employer and category.'],
        ['reports.balances', 'wallet2', 'Savings & Share Balances', 'Share capital, deposits and savings for every member.'],
        ['savings.arrears', 'calendar-x', 'Contribution Arrears', 'Expected vs actual monthly contributions.'],
        ['reports.profitability', 'graph-up', 'Member Profitability', 'Income earned vs dividends paid, per member.'],
    ],
    'Credit' => [
        ['reports.portfolio', 'pie-chart', 'Loan Portfolio', 'Balances by product.'],
        ['reports.arrears', 'exclamation-triangle', 'Arrears, Aging & PAR', 'Days overdue, SASRA classification and provisions.'],
        ['reports.guarantors', 'shield', 'Guarantor Exposure', 'Liability per guarantor, reducing as loans are repaid.'],
    ],
    'Finance' => [
        ['accounting.trial-balance', 'list-columns', 'Trial Balance', 'From the general ledger.'],
        ['accounting.profit-loss', 'cash', 'Income Statement', 'Surplus or deficit for a period.'],
        ['accounting.balance-sheet', 'bank', 'Balance Sheet', 'Statement of financial position.'],
        ['accounting.cash-flow', 'arrow-left-right', 'Cash Flow', 'Operating, investing and financing.'],
        ['accounting.ratios', 'speedometer', 'Prudential Ratios', 'Capital adequacy, liquidity and PAR vs SASRA limits.'],
        ['reports.cashbook', 'journal-text', 'Cash Book Summary', 'Money in and out by type and channel.'],
    ],
    'Compliance & security' => [
        ['reports.aml', 'incognito', 'AML Alerts', 'Large cash transactions, high-risk and PEP members.'],
        ['reports.logins', 'door-open', 'Login History', 'Successful and failed sign-ins.'],
        ['reports.audit', 'shield-check', 'Audit Trail', 'Every financial and administrative action.'],
    ],
];
@endphp
@foreach($groups as $title => $items)
    <h6 class="text-muted text-uppercase small mt-3">{{ $title }}</h6>
    <div class="row g-3">
        @foreach($items as [$route, $icon, $name, $desc])
            @if(\Illuminate\Support\Facades\Route::has($route))
            <div class="col-md-6 col-xl-3"><a href="{{ route($route) }}" class="text-decoration-none"><div class="card h-100"><div class="card-body d-flex gap-3">
                <i class="bi bi-{{ $icon }} fs-3 text-success"></i><div><div class="fw-semibold text-dark">{{ $name }}</div><div class="small text-muted">{{ $desc }}</div></div>
            </div></div></a></div>
            @endif
        @endforeach
    </div>
@endforeach
@endsection
