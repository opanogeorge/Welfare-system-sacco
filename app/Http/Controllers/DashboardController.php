<?php

namespace App\Http\Controllers;

use App\Models\AccountType;
use App\Models\Loan;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $balances = AccountType::withSum('accounts as total', 'balance')->get();
        $running = Loan::where('status', 'disbursed');

        $stats = [
            'members'         => Member::active()->count(),
            'new_this_month'  => Member::whereMonth('joined_on', now()->month)->whereYear('joined_on', now()->year)->count(),
            'loan_book'       => (float) (clone $running)->sum(DB::raw('principal_balance + interest_balance + penalty_balance')),
            'running_loans'   => (clone $running)->count(),
            'pending_loans'   => Loan::whereIn('status', ['pending', 'guarantors_pending', 'appraised', 'approved'])->count(),
            'today_deposits'  => (float) Transaction::where('type', 'deposit')->whereDate('txn_date', today())->sum('amount'),
            'today_repayments'=> (float) Transaction::where('type', 'loan_repayment')->whereDate('txn_date', today())->sum('amount'),
            'month_disbursed' => (float) Loan::whereMonth('disbursed_on', now()->month)->whereYear('disbursed_on', now()->year)->sum('amount_approved'),
        ];

        // Portfolio at risk (>30 days)
        $par = 0;
        foreach ((clone $running)->get() as $l) {
            if ($l->daysInArrears() > 30) $par += (float) $l->principal_balance;
        }
        $principalBook = (float) (clone $running)->sum('principal_balance');
        $stats['par30'] = $principalBook > 0 ? round($par / $principalBook * 100, 2) : 0;

        // Last 6 months deposits vs repayments
        $trend = collect(range(5, 0))->map(function ($i) {
            $d = now()->subMonthsNoOverflow($i);
            return [
                'label'      => $d->format('M Y'),
                'deposits'   => (float) Transaction::where('type', 'deposit')->whereYear('txn_date', $d->year)->whereMonth('txn_date', $d->month)->sum('amount'),
                'repayments' => (float) Transaction::where('type', 'loan_repayment')->whereYear('txn_date', $d->year)->whereMonth('txn_date', $d->month)->sum('amount'),
                'disbursed'  => (float) Loan::whereYear('disbursed_on', $d->year)->whereMonth('disbursed_on', $d->month)->sum('amount_approved'),
            ];
        });

        $pending = Loan::with('member', 'product')->whereIn('status', ['pending', 'appraised', 'approved'])->latest()->take(8)->get();
        $recent = Transaction::with('member')->latest()->take(10)->get();

        return view('dashboard', compact('stats', 'balances', 'trend', 'pending', 'recent'));
    }
}
