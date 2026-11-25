<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\Member;
use App\Models\MpesaTransaction;
use App\Services\AccountService;
use App\Services\LoanService;
use App\Services\MpesaPoster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Payroll check-off: the employer remits one lump sum and a schedule (CSV).
 * CSV columns: member_no, item, amount
 *   item = an account code (SHR, DEP, SAV ...) or a loan number (LN2026xxxxx)
 */
class CheckoffController extends Controller
{
    public function create()
    {
        return view('checkoff.create');
    }

    public function template()
    {
        return response()->streamDownload(function () {
            echo "member_no,item,amount\nMBR00001,DEP,2000\nMBR00001,SHR,500\nMBR00001,LN202600001,5400\n";
        }, 'checkoff-template.csv');
    }

    /**
     * Bulk posting from CSV. Channel:
     *   payroll - employer check-off (Dr Check-off receivable)
     *   bank    - bulk bank deposits (Dr Bank)
     *   opening - go-live opening balances (Dr Opening balances equity); accountant/admin only
     */
    public function store(Request $request, \App\Services\CheckoffService $service)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:5120',
            'txn_date' => 'required|date',
            'reference' => 'required|string|max:60',
            'channel' => 'nullable|in:payroll,bank,opening',
        ]);
        $channel = $request->input('channel', 'payroll');
        if ($channel === 'opening' && ! $request->user()->hasRole('accountant')) abort(403);

        try {
            $res = $service->postFile($request->file('file')->getRealPath(), $request->txn_date, $request->reference, null, $channel);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', "{$res['ok']} line(s) posted, total KES " . number_format($res['total'], 2))->with('import_errors', $res['errors']);
    }

    public function mpesa(Request $request)
    {
        $rows = MpesaTransaction::when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()->paginate(30)->withQueryString();
        return view('checkoff.mpesa', compact('rows'));
    }

    /** Manually route an unmatched M-Pesa payment to the right member/account/loan. */
    public function assignMpesa(Request $request, MpesaTransaction $mpesa, MpesaPoster $poster)
    {
        $request->validate(['bill_ref' => 'required|string|max:60']);
        if ($mpesa->status === 'posted') return back()->with('error', 'Already posted.');
        $mpesa->update(['bill_ref' => strtoupper(trim($request->bill_ref))]);
        $poster->post($mpesa);
        return back()->with($mpesa->fresh()->status === 'posted' ? 'success' : 'error',
            $mpesa->fresh()->status === 'posted' ? 'Payment posted.' : 'Still could not match that reference.');
    }
}
