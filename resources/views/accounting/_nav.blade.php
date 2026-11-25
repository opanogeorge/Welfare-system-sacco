<ul class="nav nav-pills mb-3 small no-print flex-wrap">
    @foreach([
        'accounting.accounts' => 'Chart of Accounts', 'accounting.journals' => 'Journals', 'accounting.trial-balance' => 'Trial Balance',
        'accounting.profit-loss' => 'Income Statement', 'accounting.balance-sheet' => 'Balance Sheet', 'accounting.cash-flow' => 'Cash Flow',
        'accounting.ratios' => 'Prudential Ratios', 'accounting.banks' => 'Banks & Reconciliation',
    ] as $r => $l)
        <li class="nav-item"><a class="nav-link py-1 {{ request()->routeIs($r . '*') ? 'active bg-success' : 'text-success' }}" href="{{ route($r) }}">{{ $l }}</a></li>
    @endforeach
</ul>
