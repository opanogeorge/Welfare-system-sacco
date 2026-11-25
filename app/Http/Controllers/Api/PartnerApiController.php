<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\Member;
use App\Models\PartnerPayment;
use App\Models\Transaction;
use App\Services\AccountService;
use App\Services\LoanService;
use App\Services\NumberGenerator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Partner / open API for third-party systems: banks and agents (collections), employer HR and
 * payroll systems (onboarding), data warehouses and BI tools (transactions).
 * Base: /api/v1   Auth: Authorization: Bearer sk_...   Docs: Admin > API & Webhooks.
 */
class PartnerApiController extends Controller
{
    private function client(Request $r) { return $r->attributes->get('api_client'); }

    public function ping(Request $r)
    {
        return ['ok' => true, 'client' => $this->client($r)->name, 'sacco' => config('sacco.name'), 'time' => now()->toIso8601String()];
    }

    /** GET /members/{member_no} - validate an account before accepting a payment. */
    public function member(string $memberNo)
    {
        $m = Member::where('member_no', strtoupper($memberNo))->first();
        if (! $m) return response()->json(['error' => 'Member not found'], 404);
        return ['member_no' => $m->member_no, 'name' => $m->full_name, 'status' => $m->status,
                'accounts' => $m->accounts()->with('type')->get()->map(fn ($a) => $a->type->code)];
    }

    /** GET /members/{member_no}/balances */
    public function balances(string $memberNo)
    {
        $m = Member::with('accounts.type')->where('member_no', strtoupper($memberNo))->first();
        if (! $m) return response()->json(['error' => 'Member not found'], 404);
        return [
            'member_no' => $m->member_no,
            'accounts' => $m->accounts->map(fn ($a) => ['code' => $a->type->code, 'name' => $a->type->name, 'balance' => (float) $a->balance]),
            'loans' => $m->loans()->where('status', 'disbursed')->get()->map(fn ($l) => ['loan_no' => $l->loan_no, 'balance' => $l->outstanding, 'arrears' => $l->arrears()]),
        ];
    }

    /** GET /loans/{loan_no} */
    public function loan(string $loanNo)
    {
        $l = Loan::with('member', 'product')->where('loan_no', strtoupper($loanNo))->first();
        if (! $l) return response()->json(['error' => 'Loan not found'], 404);
        $next = $l->schedules()->whereIn('status', ['pending', 'partial', 'overdue'])->orderBy('due_date')->first();
        return ['loan_no' => $l->loan_no, 'member_no' => $l->member->member_no, 'product' => $l->product->name, 'status' => $l->status,
                'principal' => (float) $l->amount_approved, 'balance' => $l->outstanding, 'arrears' => $l->status === 'disbursed' ? $l->arrears() : 0,
                'days_in_arrears' => $l->status === 'disbursed' ? $l->daysInArrears() : 0,
                'next_installment' => $next ? ['due_date' => $next->due_date->toDateString(), 'amount' => $next->balance()] : null];
    }

    /**
     * POST /payments  {reference, member_no|loan_no, account?, amount, paid_at?}
     * Idempotent on reference: re-sending the same reference returns the original result.
     */
    public function pay(Request $r, AccountService $accounts, LoanService $loans)
    {
        $d = $r->validate([
            'reference' => 'required|string|max:60', 'member_no' => 'required_without:loan_no|nullable|string', 'loan_no' => 'nullable|string',
            'account' => 'nullable|string|max:20', 'amount' => 'required|numeric|min:1|max:10000000', 'paid_at' => 'nullable|date|before_or_equal:now',
            'payer' => 'nullable|string|max:120',
        ]);
        $client = $this->client($r);
        if ($existing = PartnerPayment::where('api_client_id', $client->id)->where('reference', $d['reference'])->first()) {
            return response()->json($this->paymentJson($existing) + ['duplicate' => true], $existing->status === 'posted' ? 200 : 422);
        }

        $target = strtoupper($d['loan_no'] ?? $d['member_no']);
        $date = isset($d['paid_at']) ? Carbon::parse($d['paid_at']) : today();
        $meta = ['channel' => 'bank', 'external_ref' => $d['reference'], 'date' => $date, 'posted_by' => null,
                 'description' => "{$client->name} payment" . (isset($d['payer']) ? " from {$d['payer']}" : '')];
        $payment = new PartnerPayment(['api_client_id' => $client->id, 'reference' => $d['reference'], 'target' => $target,
            'account' => $d['account'] ?? null, 'amount' => $d['amount']]);

        try {
            DB::transaction(function () use ($d, $target, $date, $meta, $accounts, $loans, $payment) {
                if (! empty($d['loan_no'])) {
                    $loan = Loan::where('loan_no', $target)->where('status', 'disbursed')->firstOrFail();
                    $rep = $loans->repay($loan, (float) $d['amount'], 'bank', $d['reference'], $date);
                    $payment->transaction_id = Transaction::where('reference', $rep->reference)->value('id');
                } else {
                    $member = Member::where('member_no', $target)->firstOrFail();
                    $tx = $accounts->deposit($member, strtoupper($d['account'] ?? 'DEP'), (float) $d['amount'], $meta);
                    $payment->transaction_id = $tx->id;
                }
                $payment->status = 'posted';
                $payment->save();
            });
        } catch (\Throwable $e) {
            $payment->status = 'failed';
            $payment->message = $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException ? 'Member, account or running loan not found' : mb_substr($e->getMessage(), 0, 250);
            $payment->save();
            return response()->json($this->paymentJson($payment), 422);
        }
        return response()->json($this->paymentJson($payment), 201);
    }

    public function payment(Request $r, string $reference)
    {
        $p = PartnerPayment::where('api_client_id', $this->client($r)->id)->where('reference', $reference)->first();
        return $p ? $this->paymentJson($p) : response()->json(['error' => 'Not found'], 404);
    }

    /** POST /members - application from an HR / onboarding system; created as pending for staff approval. */
    public function createMember(Request $r)
    {
        $d = $r->validate([
            'first_name' => 'required|string|max:60', 'middle_name' => 'nullable|string|max:60', 'last_name' => 'required|string|max:60',
            'national_id' => 'required|string|max:20|unique:members,national_id', 'phone' => 'required|string|max:20', 'email' => 'nullable|email',
            'date_of_birth' => 'nullable|date|before:-18 years', 'gender' => 'nullable|in:male,female,other',
            'employer_code' => 'nullable|exists:employers,code', 'payroll_no' => 'nullable|string|max:30', 'monthly_contribution' => 'nullable|numeric|min:0',
        ]);
        $employer = isset($d['employer_code']) ? \App\Models\Employer::where('code', $d['employer_code'])->first() : null;
        unset($d['employer_code']);
        $m = Member::create($d + ['member_no' => NumberGenerator::memberNo(), 'joined_on' => today(), 'status' => 'pending',
            'employer_id' => $employer?->id, 'employer' => $employer?->name, 'onboarding_channel' => 'api']);
        app(AccountService::class)->openDefaultAccounts($m);
        return response()->json(['member_no' => $m->member_no, 'status' => 'pending'], 201);
    }

    /** GET /transactions?since=2026-11-01&page=1  (max 500 per page) */
    public function transactions(Request $r)
    {
        $r->validate(['since' => 'nullable|date', 'until' => 'nullable|date']);
        $q = Transaction::with('member:id,member_no', 'account.type')
            ->when($r->since, fn ($q, $s) => $q->whereDate('txn_date', '>=', $s))
            ->when($r->until, fn ($q, $s) => $q->whereDate('txn_date', '<=', $s))
            ->orderBy('id')->paginate(min(500, (int) $r->input('per_page', 200)));
        return [
            'data' => $q->getCollection()->map(fn ($t) => ['id' => $t->id, 'reference' => $t->reference, 'date' => $t->txn_date->toDateString(),
                'member_no' => $t->member?->member_no, 'account' => $t->account?->type?->code, 'loan_id' => $t->loan_id, 'type' => $t->type,
                'direction' => $t->direction, 'amount' => (float) $t->amount, 'channel' => $t->channel, 'external_ref' => $t->external_ref,
                'branch_id' => $t->branch_id, 'reversed' => $t->reversed]),
            'page' => $q->currentPage(), 'last_page' => $q->lastPage(), 'total' => $q->total(),
        ];
    }

    private function paymentJson(PartnerPayment $p): array
    {
        return ['reference' => $p->reference, 'status' => $p->status, 'target' => $p->target, 'account' => $p->account,
                'amount' => (float) $p->amount, 'message' => $p->message, 'transaction_id' => $p->transaction_id, 'created_at' => $p->created_at?->toIso8601String()];
    }
}
