<?php

namespace App\Http\Controllers;

use App\Models\FixedDeposit;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\Setting;
use App\Models\StandingOrder;
use App\Services\SavingsService;
use Illuminate\Http\Request;
use RuntimeException;

class SavingsController extends Controller
{
    public function __construct(private SavingsService $savings) {}

    public function fixedDeposits(Request $r)
    {
        $fds = FixedDeposit::with('member')->when($r->status, fn ($q, $s) => $q->where('status', $s))->latest()->paginate(30)->withQueryString();
        $totals = ['active' => FixedDeposit::where('status', 'active')->sum('amount'), 'interest' => FixedDeposit::where('status', 'active')->sum('interest_amount')];
        return view('savings.fixed-deposits', compact('fds', 'totals'));
    }

    public function storeFixedDeposit(Request $r)
    {
        $d = $r->validate(['member_id' => 'required|exists:members,id', 'amount' => 'required|numeric|min:' . (float) Setting::get('fd_min_amount', 10000),
            'rate' => 'required|numeric|min:0|max:30', 'term_months' => 'required|integer|min:1|max:60']);
        try {
            $fd = $this->savings->openFixedDeposit(Member::findOrFail($d['member_id']), (float) $d['amount'], (float) $d['rate'], (int) $d['term_months'], $r->boolean('auto_rollover'));
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
        return back()->with('success', "Fixed deposit {$fd->fd_no} opened. Matures " . $fd->maturity_date->format('d M Y') . '.');
    }

    public function closeFixedDeposit(Request $r, FixedDeposit $fd)
    {
        try { $this->savings->closeFixedDeposit($fd, $fd->maturity_date->isFuture()); } catch (RuntimeException $e) { return back()->with('error', $e->getMessage()); }
        return back()->with('success', 'Fixed deposit closed and paid to savings.');
    }

    public function standingOrders()
    {
        $orders = StandingOrder::with('member', 'fromAccount.type', 'targetAccount.type', 'loan')->latest()->paginate(30);
        return view('savings.standing-orders', compact('orders'));
    }

    public function storeStandingOrder(Request $r)
    {
        $d = $r->validate(['member_id' => 'required|exists:members,id', 'target' => 'required|string', 'amount' => 'required|numeric|min:1', 'day_of_month' => 'required|integer|min:1|max:28']);
        $member = Member::findOrFail($d['member_id']);
        $from = $member->account('SAV');
        if (! $from) return back()->with('error', 'Member has no savings account.');
        $data = ['member_id' => $member->id, 'from_account_id' => $from->id, 'amount' => $d['amount'], 'day_of_month' => $d['day_of_month']];

        if (str_starts_with($d['target'], 'LN')) {
            $loan = $member->loans()->where('loan_no', $d['target'])->where('status', 'disbursed')->first();
            if (! $loan) return back()->with('error', 'Running loan not found for this member.');
            $data += ['target_type' => 'loan', 'loan_id' => $loan->id];
        } else {
            $acc = $member->account($d['target']);
            if (! $acc || $acc->id === $from->id) return back()->with('error', 'Choose a different target account.');
            $data += ['target_type' => 'account', 'target_account_id' => $acc->id];
        }
        StandingOrder::create($data);
        return back()->with('success', 'Standing order created.');
    }

    public function toggleStandingOrder(StandingOrder $order)
    {
        $order->update(['is_active' => ! $order->is_active]);
        return back()->with('success', $order->is_active ? 'Standing order activated.' : 'Standing order stopped.');
    }

    public function runInterest(Request $r)
    {
        $r->validate(['month' => 'required|date_format:Y-m']);
        $n = $this->savings->postMonthlyInterest(\Carbon\Carbon::createFromFormat('Y-m', $r->month)->startOfMonth());
        return back()->with('success', "$n account(s) credited with interest.");
    }

    public function arrears(Request $r)
    {
        $months = (int) $r->input('months', 6);
        $rows = $this->savings->contributionArrears($months)->sortByDesc('arrears')->values();
        if ($r->export === 'csv') {
            return response()->streamDownload(function () use ($rows) {
                $o = fopen('php://output', 'w');
                fputcsv($o, ['Member No', 'Name', 'Phone', 'Monthly', 'Months', 'Expected', 'Paid', 'Arrears']);
                foreach ($rows as $x) fputcsv($o, [$x['member']->member_no, $x['member']->full_name, $x['member']->phone, $x['member']->monthly_contribution, $x['months'], $x['expected'], $x['actual'], $x['arrears']]);
                fclose($o);
            }, 'contribution-arrears.csv');
        }
        return view('savings.arrears', compact('rows', 'months'));
    }
}
