<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\DividendPeriod;
use App\Services\DividendService;
use Illuminate\Http\Request;
use RuntimeException;

class DividendController extends Controller
{
    public function __construct(private DividendService $service) {}

    public function index()
    {
        return view('dividends.index', ['periods' => DividendPeriod::orderByDesc('year')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'year' => 'required|integer|min:2000|max:' . now()->year . '|unique:dividend_periods,year',
            'dividend_rate' => 'required|numeric|min:0|max:100',
            'interest_rate' => 'required|numeric|min:0|max:100',
            'withholding_tax_rate' => 'required|numeric|min:0|max:100',
            'payout_destination' => 'required|in:savings,share_capital',
        ]);
        $p = DividendPeriod::create($data);
        return redirect()->route('dividends.show', $p);
    }

    public function show(DividendPeriod $period)
    {
        $payouts = $period->payouts()->with('member')->orderByDesc('net')->paginate(50);
        return view('dividends.show', compact('period', 'payouts'));
    }

    public function compute(DividendPeriod $period)
    {
        try { $this->service->compute($period); } catch (RuntimeException $e) { return back()->with('error', $e->getMessage()); }
        return back()->with('success', 'Dividends computed. Review then approve.');
    }

    public function approve(Request $request, DividendPeriod $period)
    {
        if ($period->status !== 'computed') return back()->with('error', 'Compute first.');
        $period->update(['status' => 'approved', 'approved_by' => $request->user()->id]);
        AuditLog::record('dividends_approved', $period);
        return back()->with('success', 'Approved. You can now post the payouts.');
    }

    public function pay(DividendPeriod $period)
    {
        try { $this->service->pay($period); } catch (RuntimeException $e) { return back()->with('error', $e->getMessage()); }
        return back()->with('success', 'Dividends credited to members.');
    }

    public function export(DividendPeriod $period)
    {
        return response()->streamDownload(function () use ($period) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Member No', 'Name', 'Share Capital', 'Avg Deposits', 'Dividend', 'Interest', 'Gross', 'WHT', 'Net']);
            foreach ($period->payouts()->with('member')->cursor() as $p) {
                fputcsv($out, [$p->member->member_no, $p->member->full_name, $p->share_balance, $p->avg_deposit_balance,
                    $p->dividend_amount, $p->interest_amount, $p->gross, $p->tax, $p->net]);
            }
            fclose($out);
        }, "dividends-{$period->year}.csv");
    }
}
