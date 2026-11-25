<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Expense;
use App\Models\FraudAlert;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\LoanRepayment;
use App\Models\LoanSchedule;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Business-intelligence dashboard: growth, portfolio quality, collections, profitability, branches. */
class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $u = $request->user();
        $branch = $u->isHq() ? $request->input('branch') : $u->branch_id;
        $months = (int) $request->input('months', 12);
        $from = now()->subMonthsNoOverflow($months - 1)->startOfMonth();
        $b = fn ($q) => $branch ? $q->where($q->getModel()->getTable() . '.branch_id', $branch) : $q;

        // ---- monthly series ----
        $labels = []; $series = ['members' => [], 'deposits' => [], 'withdrawals' => [], 'disbursed' => [], 'repaid' => [], 'due' => [], 'efficiency' => []];
        for ($i = 0; $i < $months; $i++) {
            $s = $from->copy()->addMonthsNoOverflow($i); $e = $s->copy()->endOfMonth();
            $labels[] = $s->format('M y');
            $series['members'][] = $b(Member::query())->whereBetween('joined_on', [$s, $e])->count();
            $series['deposits'][] = (float) $b(Transaction::query())->where('type', 'deposit')->whereBetween('txn_date', [$s, $e])->sum('amount');
            $series['withdrawals'][] = (float) $b(Transaction::query())->where('type', 'withdrawal')->whereBetween('txn_date', [$s, $e])->sum('amount');
            $series['disbursed'][] = (float) $b(Loan::query())->whereBetween('disbursed_on', [$s, $e])->sum('amount_approved');
            $repaid = (float) LoanRepayment::whereBetween('paid_on', [$s, $e])->when($branch, fn ($q) => $q->whereHas('loan', fn ($l) => $l->where('branch_id', $branch)))->sum('amount');
            $due = (float) LoanSchedule::whereBetween('due_date', [$s, $e])->when($branch, fn ($q) => $q->whereHas('loan', fn ($l) => $l->where('branch_id', $branch)))->sum('total_due');
            $series['repaid'][] = $repaid; $series['due'][] = $due;
            $series['efficiency'][] = $due > 0 ? round(min(150, $repaid / $due * 100), 1) : null;
        }

        // ---- portfolio quality ----
        $running = $b(Loan::query())->with('product')->where('status', 'disbursed')->get();
        $buckets = ['Current' => 0, '1-30' => 0, '31-90' => 0, '91-180' => 0, '180+' => 0];
        $book = 0; $par30 = 0; $npl = 0;
        foreach ($running as $l) {
            $d = $l->daysInArrears(); $p = (float) $l->principal_balance; $book += $p;
            $key = $d === 0 ? 'Current' : ($d <= 30 ? '1-30' : ($d <= 90 ? '31-90' : ($d <= 180 ? '91-180' : '180+')));
            $buckets[$key] += $p;
            if ($d > 30) $par30 += $p;
            if ($d > 90) $npl += $p;
        }
        $byProduct = $running->groupBy(fn ($l) => $l->product->name)->map(fn ($g) => round($g->sum('principal_balance'), 2));

        // ---- members ----
        $members = $b(Member::query())->whereIn('status', ['active', 'dormant'])->get(['gender', 'date_of_birth', 'employer_id', 'status']);
        $gender = $members->groupBy(fn ($m) => ucfirst($m->gender ?: 'unknown'))->map->count();
        $ages = $members->groupBy(function ($m) {
            $a = $m->date_of_birth?->age; return $a === null ? 'Unknown' : ($a < 25 ? '18-24' : ($a < 35 ? '25-34' : ($a < 45 ? '35-44' : ($a < 55 ? '45-54' : '55+'))));
        })->map->count()->sortKeys();
        $employers = $members->whereNotNull('employer_id')->groupBy('employer_id')->map->count()->sortDesc()->take(8)
            ->mapWithKeys(fn ($n, $id) => [optional(\App\Models\Employer::find($id))->name ?? 'Other' => $n]);

        // ---- KPIs (last 12 months) ----
        $yStart = now()->subYear();
        $interest = (float) LoanRepayment::where('paid_on', '>=', $yStart)->when($branch, fn ($q) => $q->whereHas('loan', fn ($l) => $l->where('branch_id', $branch)))->sum('interest_paid');
        $fees = (float) $b(Transaction::query())->where('type', 'fee')->where('txn_date', '>=', $yStart)->sum('amount');
        $costs = (float) $b(Expense::query())->where('spent_on', '>=', $yStart)->sum('amount');
        $active = $b(Member::query())->where('status', 'active')->count();
        $prevActive = $b(Member::query())->where('status', 'active')->where('joined_on', '<', $yStart)->count();
        $savings = (float) MemberAccount::when($branch, fn ($q) => $q->whereHas('member', fn ($m) => $m->where('branch_id', $branch)))->sum('balance');
        $kpi = [
            'members' => $active, 'growth' => $prevActive ? round(($active - $prevActive) / $prevActive * 100, 1) : null,
            'savings' => $savings, 'book' => $book, 'par30' => $book ? round($par30 / $book * 100, 2) : 0, 'npl' => $book ? round($npl / $book * 100, 2) : 0,
            'loan_yield' => $book ? round($interest / $book * 100, 1) : 0,
            'cost_income' => ($interest + $fees) > 0 ? round($costs / ($interest + $fees) * 100, 1) : null,
            'collection' => array_sum($series['due']) > 0 ? round(array_sum($series['repaid']) / array_sum($series['due']) * 100, 1) : null,
            'loans_to_savings' => $savings > 0 ? round($book / $savings * 100, 1) : null,
            'open_alerts' => FraudAlert::whereIn('status', ['open', 'investigating'])->count(),
        ];

        // ---- branch comparison (HQ only) ----
        $branches = $u->isHq() ? Branch::orderBy('name')->get()->map(function ($br) {
            $loans = Loan::where('branch_id', $br->id)->where('status', 'disbursed')->get();
            $p = (float) $loans->sum('principal_balance');
            $at = $loans->filter(fn ($l) => $l->daysInArrears() > 30)->sum('principal_balance');
            return ['branch' => $br, 'members' => Member::where('branch_id', $br->id)->where('status', 'active')->count(),
                    'savings' => (float) MemberAccount::whereHas('member', fn ($m) => $m->where('branch_id', $br->id))->sum('balance'),
                    'book' => $p, 'par30' => $p ? round($at / $p * 100, 1) : 0,
                    'deposits_month' => (float) Transaction::where('branch_id', $br->id)->where('type', 'deposit')->where('txn_date', '>=', now()->startOfMonth())->sum('amount')];
        }) : collect();

        if ($request->export === 'csv') {
            return response()->streamDownload(function () use ($labels, $series) {
                $o = fopen('php://output', 'w');
                fputcsv($o, array_merge(['Month'], array_keys($series)));
                foreach ($labels as $i => $l) fputcsv($o, array_merge([$l], array_map(fn ($s) => $s[$i], $series)));
                fclose($o);
            }, 'analytics.csv');
        }

        return view('analytics.index', compact('labels', 'series', 'buckets', 'byProduct', 'gender', 'ages', 'employers', 'kpi', 'branches', 'branch', 'months') + [
            'branchList' => $u->isHq() ? Branch::orderBy('name')->get() : collect(),
        ]);
    }
}
