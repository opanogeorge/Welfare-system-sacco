<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\LoanGuarantor;
use App\Models\Signature;
use App\Services\LoanService;
use App\Services\SignatureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class SignatureController extends Controller
{
    public function __construct(private SignatureService $sigs) {}

    /** Member signs their own loan agreement in the portal. */
    public function signLoan(Request $request, Loan $loan)
    {
        $member = $request->user()->member;
        abort_unless($member && $loan->member_id === $member->id, 404);
        $request->validate(['signature' => 'required|string', 'agree' => 'accepted']);
        try {
            $this->sigs->sign($loan->load('member', 'product'), 'loan_agreement', $request->signature, $member->full_name, 'borrower', $member);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', 'Loan agreement signed.');
    }

    /** Staff capture the borrower's signature on a tablet at the counter. */
    public function signLoanInOffice(Request $request, Loan $loan)
    {
        $request->validate(['signature' => 'required|string', 'signer' => 'required|in:borrower,witness']);
        try {
            $name = $request->signer === 'borrower' ? $loan->member->full_name : $request->user()->name;
            $this->sigs->sign($loan->load('member', 'product'), 'loan_agreement', $request->signature, $name, $request->signer, $request->signer === 'borrower' ? $loan->member : null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', ucfirst($request->signer) . ' signature captured.');
    }

    /** Guarantor accepts AND signs in one step from the portal. */
    public function signGuarantee(Request $request, LoanGuarantor $guarantor, LoanService $loans)
    {
        $member = $request->user()->member;
        abort_unless($member && $guarantor->member_id === $member->id, 404);
        $request->validate(['signature' => 'required|string', 'agree' => 'accepted']);
        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($guarantor, $loans, $request, $member) {
                $loans->respondGuarantee($guarantor, true, 'esign');
                $this->sigs->sign($guarantor->fresh(['loan.member', 'member']), 'guarantee', $request->signature, $member->full_name, 'guarantor', $member);
            });
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', 'Guarantee accepted and signed.');
    }

    /** Printable / PDF loan agreement with embedded signatures and verification codes. */
    public function agreement(Request $request, Loan $loan)
    {
        $u = $request->user();
        abort_unless($u->isStaff() || $u->member?->id === $loan->member_id, 403);
        $loan->load('member', 'product', 'guarantors.member');
        $calc = \App\Services\LoanCalculator::schedule((float) ($loan->amount_approved ?? $loan->amount_applied), (float) $loan->interest_rate, $loan->period, $loan->interest_method);
        $signatures = Signature::where('signable_type', Loan::class)->where('signable_id', $loan->id)->get()
            ->map(fn ($s) => ['sig' => $s, 'img' => $this->sigs->imageDataUri($s)]);
        $guarantees = Signature::where('signable_type', LoanGuarantor::class)->whereIn('signable_id', $loan->guarantors->pluck('id'))->get()
            ->map(fn ($s) => ['sig' => $s, 'img' => $this->sigs->imageDataUri($s)]);
        $data = compact('loan', 'calc', 'signatures', 'guarantees');
        if ($request->boolean('pdf') && class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return \Barryvdh\DomPDF\Facade\Pdf::loadView('signatures.agreement', $data + ['pdf' => true])->download("loan-agreement-{$loan->loan_no}.pdf");
        }
        return view('signatures.agreement', $data + ['pdf' => false]);
    }

    /** Public: anyone holding a signed document can confirm it is genuine and unaltered. */
    public function verify(Request $request, ?string $code = null)
    {
        $code = $code ?? $request->query('code');
        $result = $code ? $this->sigs->verify($code) : null;
        return view('signatures.verify', ['code' => $code, 'result' => $result]);
    }

    public function image(Signature $signature)
    {
        $u = request()->user();
        abort_unless($u->isStaff() || $u->member?->id === $signature->member_id, 403);
        return Storage::disk('local')->response($signature->image_path);
    }
}
