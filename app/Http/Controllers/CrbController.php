<?php

namespace App\Http\Controllers;

use App\Models\CrbCheck;
use App\Models\Loan;
use App\Models\Member;
use App\Services\CrbService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class CrbController extends Controller
{
    public function __construct(private CrbService $crb) {}

    public function index(Request $request)
    {
        $checks = CrbCheck::with('member', 'checker')->latest()->paginate(30);
        return view('crb.index', ['checks' => $checks, 'configured' => $this->crb->configured()]);
    }

    public function run(Request $request, Member $member)
    {
        $d = $request->validate(['type' => 'required|in:score,delinquency,identity,report', 'consent' => 'accepted', 'loan_id' => 'nullable|exists:loans,id']);
        try {
            $c = $this->crb->check($member, $d['type'], isset($d['loan_id']) ? Loan::find($d['loan_id']) : null, true);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with($c->status === 'success' ? 'success' : 'error', "CRB {$d['type']} check: {$c->summary}" . ($c->score ? " (score {$c->score})" : ''));
    }

    public function manual(Request $request, Member $member)
    {
        $d = $request->validate(['provider' => 'required|in:metropol,transunion,creditinfo', 'score' => 'nullable|integer|min:0|max:999',
            'grade' => 'nullable|string|max:10', 'is_listed' => 'nullable|boolean', 'summary' => 'nullable|string|max:255',
            'report' => 'nullable|file|mimes:pdf|max:10240', 'consent' => 'accepted', 'loan_id' => 'nullable|exists:loans,id']);
        $this->crb->recordManual($member, $d, isset($d['loan_id']) ? Loan::find($d['loan_id']) : null, $request->file('report'));
        return back()->with('success', 'CRB result recorded.');
    }

    public function report(CrbCheck $check)
    {
        abort_unless($check->report_path, 404);
        return Storage::disk('local')->download($check->report_path, "crb-{$check->member->member_no}.pdf");
    }

    /** Monthly credit-information-sharing file for the bureaus. */
    public function export()
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            $first = true;
            foreach ($this->crb->sharingRows() as $row) {
                if ($first) { fputcsv($out, array_keys($row)); $first = false; }
                fputcsv($out, $row);
            }
            fclose($out);
        }, 'credit-information-sharing-' . now()->format('Ym') . '.csv');
    }
}
