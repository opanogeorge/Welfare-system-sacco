<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Loan;
use App\Models\LoanGuarantor;
use App\Models\LoanProduct;
use App\Models\LoanRepayment;
use App\Models\LoanSchedule;
use App\Models\Member;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LoanService
{
    public function __construct(private AccountService $accounts, private SmsService $sms) {}

    /** How much a member can borrow under a product right now, with the reasons. */
    public function eligibility(Member $member, LoanProduct $product, ?Loan $topUpOf = null): array
    {
        $deposits = $member->loanableDeposits();
        $outstanding = $member->outstandingLoans() - ($topUpOf ? $topUpOf->outstanding : 0);
        $byMultiplier = $deposits * (float) $product->deposit_multiplier - $outstanding;
        $max = max(0, min((float) $product->max_amount, $byMultiplier));

        $issues = [];
        if ($member->status !== 'active') $issues[] = 'Member is not active.';
        if ($member->monthsOfMembership() < $product->min_membership_months)
            $issues[] = "Requires {$product->min_membership_months} months of membership.";
        if ($max < (float) $product->min_amount)
            $issues[] = 'Deposits too low for the minimum loan amount (KES ' . number_format($product->min_amount) . ').';
        $defaulted = $member->loans()->where('status', 'disbursed')->get()->first(fn ($l) => $l->daysInArrears() > 90);
        if ($defaulted) $issues[] = "Loan {$defaulted->loan_no} is over 90 days in arrears.";
        if ($topUpOf) {
            $minPaid = (float) Setting::get('top_up_min_paid_percent', 50);
            $paidPct = (float) $topUpOf->amount_approved > 0
                ? (1 - (float) $topUpOf->principal_balance / (float) $topUpOf->amount_approved) * 100 : 0;
            if ($topUpOf->member_id !== $member->id || $topUpOf->status !== 'disbursed') $issues[] = 'Only a running loan of this member can be topped up.';
            elseif ($paidPct < $minPaid) $issues[] = sprintf('Top-up needs at least %s%% of the principal repaid (currently %.1f%%).', $minPaid, $paidPct);
        }

        return [
            'deposits'    => $deposits,
            'outstanding' => $outstanding,
            'max_amount'  => round($max, 2),
            'eligible'    => empty($issues),
            'issues'      => $issues,
        ];
    }

    /**
     * @param array<int, array{member_id:int, amount:float}> $guarantors
     */
    public function apply(Member $member, LoanProduct $product, float $amount, int $period, ?string $purpose, array $guarantors = [], ?Loan $topUpOf = null): Loan
    {
        $e = $this->eligibility($member, $product, $topUpOf);
        if (! $e['eligible']) throw new RuntimeException(implode(' ', $e['issues']));
        if ($amount > $e['max_amount']) throw new RuntimeException('Maximum you qualify for is KES ' . number_format($e['max_amount'], 2));
        if ($topUpOf && $amount <= $topUpOf->outstanding) throw new RuntimeException('Top-up amount must be more than the current balance of KES ' . number_format($topUpOf->outstanding, 2));
        if ($amount < $product->min_amount) throw new RuntimeException('Below product minimum.');
        if ($period < $product->min_period || $period > $product->max_period)
            throw new RuntimeException("Period must be between {$product->min_period} and {$product->max_period} months.");
        if ($product->max_active_per_member > 0) {
            $active = $member->loans()->where('loan_product_id', $product->id)->whereIn('status', ['pending', 'guarantors_pending', 'appraised', 'approved', 'disbursed'])
                ->when($topUpOf, fn ($q) => $q->where('id', '!=', $topUpOf->id))->count();
            if ($active >= $product->max_active_per_member) throw new RuntimeException("You can only have {$product->max_active_per_member} {$product->name} loan(s) at a time.");
        }
        if ($topUpOf && ! $product->allow_top_up) throw new RuntimeException("{$product->name} does not allow top-ups.");
        if (count($guarantors) < $product->min_guarantors)
            throw new RuntimeException("This product needs at least {$product->min_guarantors} guarantor(s).");

        return DB::transaction(function () use ($member, $product, $amount, $period, $purpose, $guarantors, $topUpOf) {
            $loan = Loan::create([
                'top_up_of'       => $topUpOf?->id,
                'approvals_required' => $this->approvalsRequired($amount),
                'loan_no'         => NumberGenerator::loanNo(),
                'member_id'       => $member->id,
                'loan_product_id' => $product->id,
                'amount_applied'  => $amount,
                'interest_rate'   => $product->interest_rate,
                'interest_method' => $product->interest_method,
                'grace_period_months' => $product->grace_period_months ?? 0,
                'period'          => $period,
                'purpose'         => $purpose,
                'status'          => $product->min_guarantors > 0 ? 'guarantors_pending' : 'pending',
                'applied_on'      => today(),
            ]);

            foreach ($guarantors as $g) {
                // eligibility (active, not blocked, limits, arrears, max loans, mutual guarantees...) is checked in GuarantorService
                app(GuarantorService::class)->request($loan, Member::findOrFail($g['member_id']), (float) $g['amount']);
            }

            // Snapshot the risk features as they are today (used for scoring and future model training)
            try {
                $risk = app(CreditRiskService::class);
                $installment = LoanCalculator::schedule($amount, (float) $product->interest_rate, $period, $product->interest_method)['installment'];
                $loan->forceFill(['risk_features' => $risk->features($member, $amount, $period, $installment,
                    (float) collect($guarantors)->sum('amount'), (bool) $topUpOf)])->saveQuietly();
                $risk->scoreLoan($loan);
            } catch (\Throwable $e) {
                report($e);
            }

            AuditLog::record('loan_applied', $loan, ['amount' => $amount]);
            return $loan;
        });
    }

    public function respondGuarantee(LoanGuarantor $g, bool $accept, string $channel = 'portal', ?string $reason = null): void
    {
        if ($g->status === 'expired' || ($g->status === 'pending' && $g->expires_at?->isPast())) {
            $g->update(['status' => 'expired']);
            throw new RuntimeException('This guarantee request has expired. The borrower needs to send a new request.');
        }
        if ($g->status !== 'pending') throw new RuntimeException('Already responded.');
        if ($accept) app(GuarantorService::class)->assertEligible($g->member, $g->loan->member, (float) $g->amount, $g->loan, $g);

        $g->update(['status' => $accept ? 'accepted' : 'declined', 'responded_at' => now(), 'consent_channel' => $channel,
            'consent_ip' => request()?->ip(), 'decline_reason' => $accept ? null : $reason]);
        $loan = $g->loan->fresh('guarantors');

        if ($accept) {
            foreach (LoanGuarantor::where('replaced_by_id', $g->id)->where('status', 'accepted')->get() as $old) {
                app(GuarantorService::class)->release($old, null, "Replaced by {$g->member->member_no}", true);
            }
        }

        if ($loan->status === 'guarantors_pending') {
            $accepted = $loan->guarantors->where('status', 'accepted');
            $covered = $accepted->sum('amount') + $loan->member->freeGuaranteeCapacity();
            if ($accepted->count() >= $loan->product->min_guarantors && $covered >= (float) $loan->amount_applied) {
                $loan->update(['status' => 'pending']);
            }
        }
        $this->sms->send($loan->member->phone, "{$g->member->full_name} has " . ($accept ? 'ACCEPTED' : 'DECLINED') . " to guarantee your loan {$loan->loan_no}."
            . ($accept ? '' : ' Please add another guarantor in the member portal.'), $loan->member_id);
    }

    /**
     * Affordability ("one-third rule"): all payroll deductions including the new installment
     * should not exceed the product's max_deduction_ratio of gross pay (default 2/3, leaving 1/3 take-home).
     */
    public function affordability(Loan $loan, float $gross, float $otherDeductions): array
    {
        $calc = LoanCalculator::schedule((float) ($loan->amount_approved ?? $loan->amount_applied), (float) $loan->interest_rate, $loan->period, $loan->interest_method);
        $installment = (float) $calc['installment'];
        $ratio = $gross > 0 ? round(($otherDeductions + $installment) / $gross * 100, 2) : null;
        $limit = (float) ($loan->product->max_deduction_ratio ?: 66.67);
        $maxInstallment = max(0, $gross * $limit / 100 - $otherDeductions);
        return ['installment' => $installment, 'ratio' => $ratio, 'limit' => $limit, 'ok' => $ratio !== null && $ratio <= $limit,
                'take_home' => $gross - $otherDeductions - $installment, 'max_installment' => round($maxInstallment, 2)];
    }

    public function appraise(Loan $loan, User $by, ?string $remarks, array $pay = [], bool $override = false): void
    {
        $this->assertStatus($loan, ['pending']);
        $aff = null;
        if (! empty($pay['gross_pay'])) {
            $aff = $this->affordability($loan, (float) $pay['gross_pay'], (float) ($pay['other_deductions'] ?? 0));
            if (! $aff['ok'] && ! $override) {
                throw new RuntimeException(sprintf('Fails the affordability test: deductions would take %.1f%% of gross pay (limit %.1f%%). The most this member can repay monthly is KES %s. Reduce the amount, extend the period, or tick "override" with a reason.',
                    $aff['ratio'], $aff['limit'], number_format($aff['max_installment'], 2)));
            }
        }
        $score = $this->creditScore($loan->member);
        $loan->update(['status' => 'appraised', 'appraised_by' => $by->id, 'appraised_at' => now(),
            'remarks' => trim($remarks . ($aff && ! $aff['ok'] ? ' [Affordability override]' : '')), 'credit_score' => $score['score'],
            'gross_pay' => $pay['gross_pay'] ?? null, 'net_pay' => $pay['net_pay'] ?? null, 'other_deductions' => $pay['other_deductions'] ?? null,
            'affordability_ratio' => $aff['ratio'] ?? null]);
        // Re-score with accepted guarantors now known
        try {
            $risk = app(CreditRiskService::class);
            $loan->forceFill(['risk_features' => $risk->snapshot($loan->fresh(['member', 'guarantors', 'product']))])->saveQuietly();
            $risk->scoreLoan($loan);
        } catch (\Throwable $e) {
            report($e);
        }
        AuditLog::record('loan_appraised', $loan, ['score' => $score['score']]);
    }

    /** Number of distinct approvers needed, from the amount thresholds in Settings. */
    public function approvalsRequired(float $amount): int
    {
        $levels = 1;
        foreach (['approval_level2_threshold', 'approval_level3_threshold'] as $k) {
            $t = (float) Setting::get($k, 0);
            if ($t > 0 && $amount > $t) $levels++;
        }
        return $levels;
    }

    /**
     * Multi-level approval. Each level must be a different user from the appraiser and from
     * earlier approvers; the final level (3) requires the admin/board-chair role.
     */
    public function approve(Loan $loan, User $by, float $amount, ?string $remarks): void
    {
        $this->assertStatus($loan, ['appraised', 'pending']);
        if ($amount <= 0 || $amount > (float) $loan->amount_applied) throw new RuntimeException('Approved amount must be between 1 and the applied amount.');
        if ($loan->appraised_by && $loan->appraised_by === $by->id && ! $by->hasRole('admin'))
            throw new RuntimeException('Maker-checker: the appraiser cannot approve the same loan.');
        if ($loan->approvals()->where('user_id', $by->id)->exists())
            throw new RuntimeException('You have already approved this loan. The next level needs a different approver.');

        if (Setting::get('require_crb_check') && ! app(CrbService::class)->latest($loan->member, 30)) {
            throw new RuntimeException('A CRB check from the last 30 days is required before approval (Settings: require_crb_check).');
        }
        if ($loan->product->collateral_required && ! $loan->collaterals()->where('status', 'held')->exists()) {
            throw new RuntimeException("{$loan->product->name} requires collateral. Record it on the loan before approval.");
        }
        $level = $loan->approvals()->count() + 1;
        $required = max(1, (int) $loan->approvals_required);
        if ($level === 3 && ! $by->hasPermission('loans.approve_final')) throw new RuntimeException('Level 3 approval needs the "Final-level (board) loan approval" permission (Board Chair / Administrator).');

        DB::transaction(function () use ($loan, $by, $amount, $remarks, $level, $required) {
            $loan->approvals()->create(['level' => $level, 'user_id' => $by->id, 'amount' => $amount, 'remarks' => $remarks]);
            $note = trim(($loan->remarks ? $loan->remarks . "\n" : '') . "L$level ({$by->name}): $remarks");

            if ($level >= $required) {
                $final = (float) $loan->approvals()->min('amount'); // never more than the lowest approved amount
                $loan->update(['status' => 'approved', 'amount_approved' => $final, 'approved_by' => $by->id,
                    'approved_at' => now(), 'remarks' => $note]);
                AuditLog::record('loan_approved', $loan, ['amount' => $final, 'levels' => $required]);
                $this->sms->send($loan->member->phone, "Your loan {$loan->loan_no} of KES " . number_format($final) . ' has been approved and will be disbursed shortly.', $loan->member_id);
            } else {
                $loan->update(['status' => 'appraised', 'remarks' => $note]);
                AuditLog::record('loan_approval_level', $loan, ['level' => $level, 'of' => $required]);
            }
        });
    }

    /**
     * Rule-based credit score (0-100) from SACCO data:
     * membership length, saving consistency, repayment history, debt-to-deposit ratio and current arrears.
     */
    public function creditScore(Member $member): array
    {
        $months = $member->monthsOfMembership();
        $tenure = min(20, $months);

        $depositMonths = Transaction::where('member_id', $member->id)->where('type', 'deposit')
            ->whereDate('txn_date', '>=', today()->subMonths(12))->pluck('txn_date')
            ->map(fn ($d) => $d->format('Y-m'))->unique()->count();
        $saving = round(min(12, $depositMonths) / 12 * 25);

        $loans = $member->loans()->whereIn('status', ['disbursed', 'closed'])->get();
        $maxArrears = $loans->where('status', 'disbursed')->map->daysInArrears()->max() ?? 0;
        $closed = $loans->where('status', 'closed')->count();
        $history = match (true) {
            $maxArrears > 90 => 0, $maxArrears > 30 => 8, $maxArrears > 0 => 18,
            default => min(30, 22 + $closed * 2),
        };

        $deposits = max(1, $member->loanableDeposits());
        $ratio = $member->outstandingLoans() / $deposits;
        $debt = round(25 * (1 - min(1, $ratio / 3)));

        $score = (int) max(0, min(100, $tenure + $saving + $history + $debt));
        return ['score' => $score, 'factors' => [
            'Membership tenure' => "$tenure / 20 ($months months)",
            'Saving consistency' => "$saving / 25 ($depositMonths of last 12 months)",
            'Repayment history' => "$history / 30 (max arrears {$maxArrears}d, $closed loans cleared)",
            'Debt to deposits' => "$debt / 25 (ratio " . number_format($ratio, 2) . ')',
        ]];
    }

    /** Restructure: re-amortise the remaining principal over a new period (and optionally a new rate). */
    public function reschedule(Loan $loan, User $by, int $newPeriod, Carbon $firstDue, ?float $newRate = null, ?string $reason = null): void
    {
        $this->assertStatus($loan, ['disbursed']);
        if ($newPeriod < 1 || $newPeriod > 120) throw new RuntimeException('Invalid period.');
        $principal = (float) $loan->principal_balance;
        if ($principal <= 0) throw new RuntimeException('No principal left to reschedule.');
        $rate = $newRate ?? (float) $loan->interest_rate;

        DB::transaction(function () use ($loan, $by, $newPeriod, $firstDue, $rate, $principal, $reason) {
            $loan->schedules()->whereIn('status', ['pending', 'partial', 'overdue'])->update(['status' => 'rescheduled']);
            $calc = LoanCalculator::schedule($principal, $rate, $newPeriod, $loan->interest_method, $firstDue);
            $start = (int) $loan->schedules()->max('installment_no');
            foreach ($calc['rows'] as $i => $r) {
                $r['installment_no'] = $start + $r['installment_no'];
                if ($i === 0 && (float) $loan->penalty_balance > 0) $r['penalty_due'] = $loan->penalty_balance; // carry penalties forward
                $loan->schedules()->create($r);
            }
            $loan->update([
                'interest_rate' => $rate, 'period' => $newPeriod, 'interest_balance' => $calc['total_interest'],
                'maturity_date' => end($calc['rows'])['due_date'], 'rescheduled_at' => now(),
                'reschedule_count' => $loan->reschedule_count + 1,
                'remarks' => trim($loan->remarks . "\nRescheduled by {$by->name} on " . today()->format('d/m/Y') . ": $reason"),
            ]);
            AuditLog::record('loan_rescheduled', $loan, ['period' => $newPeriod, 'rate' => $rate, 'principal' => $principal]);
        });
        $this->sms->send($loan->member->phone, "Your loan {$loan->loan_no} has been rescheduled. New installment KES "
            . number_format($loan->schedules()->where('status', 'pending')->orderBy('installment_no')->value('total_due'), 2) . '.', $loan->member_id);
    }

    /** Swap a guarantor (e.g. one who is exiting). New guarantor starts as pending until they consent. */
    /**
     * Swap a guarantor. The new guarantor must pass the eligibility checks and consent.
     * Declined/expired/pending rows are simply replaced; an ACTIVE guarantor stays liable until the replacement accepts
     * (see acceptance hook in respondGuarantee via replaced_by_id).
     */
    public function replaceGuarantor(LoanGuarantor $old, Member $new, float $amount): LoanGuarantor
    {
        return DB::transaction(function () use ($old, $new, $amount) {
            $g = app(GuarantorService::class)->request($old->loan, $new, $amount, 'replacement');
            if ($old->status === 'accepted') {
                $old->update(['replaced_by_id' => $g->id]);           // released automatically once the replacement accepts
            } else {
                $old->update(['status' => in_array($old->status, ['pending']) ? 'withdrawn' : $old->status, 'replaced_by_id' => $g->id]);
            }
            AuditLog::record('guarantor_replaced', $old->loan, ['old' => $old->member_id, 'new' => $new->id]);
            return $g;
        });
    }

    public function releaseGuarantor(LoanGuarantor $g, ?User $by = null, string $reason = 'Released by the SACCO'): void
    {
        app(GuarantorService::class)->release($g, $by, $reason);
    }

    public function reject(Loan $loan, User $by, string $reason): void
    {
        $this->assertStatus($loan, ['pending', 'guarantors_pending', 'appraised', 'approved']);
        $loan->update(['status' => 'rejected', 'remarks' => $reason, 'approved_by' => $by->id, 'approved_at' => now()]);
        app(GuarantorService::class)->withdrawPending($loan);
        $loan->guarantors()->update(['status' => 'released']);
        AuditLog::record('loan_rejected', $loan, ['reason' => $reason]);
        $this->sms->send($loan->member->phone, "Your loan application {$loan->loan_no} was not approved. Reason: $reason");
    }

    /**
     * Disburse an approved loan. Channel "savings" credits the member's FOSA/savings account,
     * any other channel (bank, mpesa, cheque) just records the payout.
     */
    public function disburse(Loan $loan, User $by, string $channel, ?Carbon $date = null, ?Carbon $firstDue = null, ?string $externalRef = null): void
    {
        $this->assertStatus($loan, ['approved']);
        $date = $date ?? today();
        $firstDue = $firstDue ?? $date->copy()->addMonthNoOverflow();
        $product = $loan->product;
        $principal = (float) $loan->amount_approved;

        $calc = LoanCalculator::schedule($principal, (float) $loan->interest_rate, $loan->period, $loan->interest_method, $firstDue);
        // Go-live migration of an existing loan: no fees, no cash movement.
        $processing = $channel === 'opening' ? 0 : round($principal * $product->processing_fee_rate / 100, 2);
        $insurance = $channel === 'opening' ? 0 : round($principal * $product->insurance_rate / 100, 2);
        $net = round($principal - $processing - $insurance, 2);

        // Top-up: the old loan is cleared out of the new principal.
        $payoff = 0;
        $old = $loan->topUpOf;
        if ($old && $old->status === 'disbursed') {
            $payoff = $old->outstanding;
            if ($payoff >= $net) throw new RuntimeException('Net disbursement does not cover the balance of the loan being topped up.');
            $net = round($net - $payoff, 2);
        }

        DB::transaction(function () use ($loan, $by, $channel, $date, $calc, $processing, $insurance, $net, $principal, $externalRef, $payoff, $old) {
            if ($payoff > 0) {
                app(AccountingService::class)->post($date, "Top-up offset: {$loan->loan_no} clears {$old->loan_no}", [
                    [app(AccountingService::class)->gl('loans_receivable'), $payoff, 0, "Top-up of {$old->loan_no}", $loan->member_id],
                    [app(AccountingService::class)->gl('clearing'), 0, $payoff, "Top-up of {$old->loan_no}", $loan->member_id],
                ], $loan);
                $this->repay($old, $payoff, 'internal', $loan->loan_no, $date, $by->id);
                $loan->top_up_payoff = $payoff;
            }

            foreach ($calc['rows'] as $r) {
                $loan->schedules()->create($r);
            }

            $loan->update([
                'status'              => 'disbursed',
                'processing_fee'      => $processing,
                'insurance_fee'       => $insurance,
                'net_disbursed'       => $net,
                'total_interest'      => $calc['total_interest'],
                'total_payable'       => $calc['total_payable'],
                'principal_balance'   => $principal,
                'interest_balance'    => $calc['total_interest'],
                'disbursed_by'        => $by->id,
                'disbursed_on'        => $date,
                'disbursement_channel'=> $channel,
                'first_due_date'      => $calc['rows'][0]['due_date'],
                'maturity_date'       => end($calc['rows'])['due_date'],
            ]);

            $ref = NumberGenerator::reference('LD');
            if ($channel === 'savings') {
                $acc = $this->accounts->accountFor($loan->member, Setting::get('disbursement_account', 'SAV'));
                $this->accounts->post($acc, 'loan_disbursement', 'credit', $net, [
                    'reference' => $ref, 'loan_id' => $loan->id, 'channel' => 'internal', 'date' => $date,
                    'description' => "Loan {$loan->loan_no} disbursement (net of fees)",
                ]);
            } else {
                Transaction::create([
                    'reference' => $ref, 'member_id' => $loan->member_id, 'loan_id' => $loan->id,
                    'type' => 'loan_disbursement', 'direction' => 'debit', 'amount' => $net,
                    'channel' => $channel, 'external_ref' => $externalRef, 'txn_date' => $date, 'posted_by' => $by->id,
                    'description' => "Loan {$loan->loan_no} paid out via $channel",
                ]);
            }
            foreach (['processing fee' => [$processing, 'PROCESSING'], 'insurance' => [$insurance, 'INSURANCE']] as $label => [$fee, $tag]) {
                if ($fee > 0) {
                    Transaction::create([
                        'reference' => $ref, 'member_id' => $loan->member_id, 'loan_id' => $loan->id, 'external_ref' => $tag,
                        'type' => 'fee', 'direction' => 'debit', 'amount' => $fee, 'channel' => 'internal',
                        'txn_date' => $date, 'posted_by' => $by->id, 'description' => "Loan $label - {$loan->loan_no}",
                    ]);
                }
            }
            AuditLog::record('loan_disbursed', $loan, ['net' => $net]);
        });

        app(WebhookService::class)->dispatch('loan.disbursed', ['loan_no' => $loan->loan_no, 'member_no' => $loan->member->member_no,
            'product' => $product->code, 'principal' => $principal, 'net' => $net, 'channel' => $channel, 'date' => $date->toDateString(),
            'maturity' => end($calc['rows'])['due_date'], 'installment' => $calc['installment']]);
        $this->sms->send($loan->member->phone, "Loan {$loan->loan_no} disbursed. Net KES " . number_format($net, 2)
            . ". Monthly installment KES " . number_format($calc['installment'], 2) . ", first due {$calc['rows'][0]['due_date']}.");
    }

    /**
     * Allocate a repayment oldest installment first: penalty -> interest -> principal.
     * Any overpayment beyond the full outstanding is credited to the member's savings.
     */
    public function repay(Loan $loan, float $amount, string $channel = 'cash', ?string $externalRef = null, ?Carbon $date = null, ?int $postedBy = null): LoanRepayment
    {
        if ($loan->status === 'written_off') return $this->recoverWrittenOff($loan, $amount, $channel, $externalRef, $date, $postedBy);
        $this->assertStatus($loan, ['disbursed']);
        if ($amount <= 0) throw new RuntimeException('Amount must be greater than zero.');
        $date = $date ?? today();

        return DB::transaction(function () use ($loan, $amount, $channel, $externalRef, $date, $postedBy) {
            $loan = Loan::whereKey($loan->id)->lockForUpdate()->first();
            $left = $amount;
            $paid = ['penalty' => 0, 'interest' => 0, 'principal' => 0];

            foreach ($loan->schedules()->whereIn('status', ['pending', 'partial', 'overdue'])->orderBy('installment_no')->get() as $s) {
                foreach (['penalty', 'interest', 'principal'] as $part) {
                    $due = (float) $s->{"{$part}_due"} - (float) $s->{"{$part}_paid"};
                    $pay = min($due, $left);
                    if ($pay > 0) {
                        $s->{"{$part}_paid"} = round((float) $s->{"{$part}_paid"} + $pay, 2);
                        $paid[$part] += $pay;
                        $left = round($left - $pay, 2);
                    }
                }
                $s->status = $s->balance() <= 0.009 ? 'paid' : 'partial';
                if ($s->status === 'paid') $s->paid_on = $date;
                $s->save();
                if ($left <= 0) break;
            }

            $loan->penalty_balance   = round(max(0, $loan->penalty_balance - $paid['penalty']), 2);
            $loan->interest_balance  = round(max(0, $loan->interest_balance - $paid['interest']), 2);
            $loan->principal_balance = round(max(0, $loan->principal_balance - $paid['principal']), 2);
            $loan->total_paid        = round($loan->total_paid + $amount - $left, 2);

            $ref = NumberGenerator::reference('LR');
            $repayment = $loan->repayments()->create([
                'reference' => $ref, 'amount' => $amount,
                'penalty_paid' => $paid['penalty'], 'interest_paid' => $paid['interest'], 'principal_paid' => $paid['principal'],
                'overpayment' => $left, 'channel' => $channel, 'external_ref' => $externalRef,
                'paid_on' => $date, 'posted_by' => $postedBy ?? auth()->id(),
            ]);

            Transaction::create([
                'reference' => $ref, 'member_id' => $loan->member_id, 'loan_id' => $loan->id,
                'type' => 'loan_repayment', 'direction' => 'credit', 'amount' => $amount - $left,
                'balance_after' => $loan->outstanding, 'channel' => $channel, 'external_ref' => $externalRef,
                'txn_date' => $date, 'posted_by' => $postedBy ?? auth()->id(),
                'description' => "Repayment {$loan->loan_no}",
            ]);

            if ($left > 0) {
                $acc = $this->accounts->accountFor($loan->member, 'SAV');
                $this->accounts->post($acc, 'deposit', 'credit', $left, [
                    'reference' => $ref, 'channel' => 'internal', 'date' => $date, 'posted_by' => $postedBy,
                    'description' => "Overpayment on {$loan->loan_no}",
                ]);
            }

            if ($loan->outstanding <= 0.009) {
                $loan->status = 'closed';
                $loan->closed_on = $date;
            }
            $loan->save();
            if ($loan->status === 'closed') app(GuarantorService::class)->releaseAllForClosedLoan($loan);

            AuditLog::record('loan_repayment', $loan, ['amount' => $amount]);
            return $repayment;
        });
    }

    /** Charge penalty on installments overdue by more than the grace period. Run daily. */
    public function applyPenalties(?Carbon $asAt = null): int
    {
        $asAt = $asAt ?? today();
        $grace = (int) Setting::get('penalty_grace_days', 5);
        $count = 0;

        LoanSchedule::with('loan.product')
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->whereDate('due_date', '<', $asAt->copy()->subDays($grace))
            ->whereHas('loan', fn ($q) => $q->where('status', 'disbursed'))
            ->chunkById(200, function ($rows) use ($asAt, &$count) {
                foreach ($rows as $s) {
                    // charge once per month of lateness
                    if ($s->penalty_applied_on && $s->penalty_applied_on->gt($asAt->copy()->subMonth())) {
                        if ($s->status !== 'overdue') $s->update(['status' => 'overdue']);
                        continue;
                    }
                    $rate = (float) $s->loan->product->penalty_rate;
                    $unpaid = ((float) $s->principal_due - (float) $s->principal_paid) + ((float) $s->interest_due - (float) $s->interest_paid);
                    $penalty = round($unpaid * $rate / 100, 2);

                    DB::transaction(function () use ($s, $penalty, $asAt) {
                        $s->update(['status' => 'overdue', 'penalty_due' => (float) $s->penalty_due + $penalty, 'penalty_applied_on' => $asAt]);
                        if ($penalty > 0) {
                            $s->loan->increment('penalty_balance', $penalty);
                            Transaction::create([
                                'reference' => NumberGenerator::reference('PN'), 'member_id' => $s->loan->member_id,
                                'loan_id' => $s->loan_id, 'type' => 'penalty', 'direction' => 'debit', 'amount' => $penalty,
                                'channel' => 'internal', 'txn_date' => $asAt,
                                'description' => "Late payment penalty - installment #{$s->installment_no}",
                            ]);
                        }
                    });
                    $count++;
                }
            });

        return $count;
    }

    /** Recover a defaulted loan from guarantors' deposits, pro-rata to what each guaranteed. */
    public function recoverFromGuarantors(Loan $loan, User $by): float
    {
        $this->assertStatus($loan, ['disbursed']);
        $guarantors = $loan->guarantors()->where('status', 'accepted')->with('member')->get();
        $total = (float) $guarantors->sum('amount');
        if ($total <= 0) throw new RuntimeException('No accepted guarantors on this loan.');

        $outstanding = $loan->outstanding;
        $recovered = 0;
        foreach ($guarantors as $g) {
            $share = round($outstanding * ((float) $g->amount / $total), 2);
            $acc = $this->accounts->accountFor($g->member, 'DEP');
            $take = min($share, (float) $acc->balance);
            if ($take <= 0) continue;
            $this->accounts->post($acc, 'transfer_out', 'debit', $take, [
                'channel' => 'internal', 'loan_id' => $loan->id, 'posted_by' => $by->id,
                'description' => "Guarantor recovery for {$loan->loan_no} ({$loan->member->member_no})",
            ]);
            $this->repay($loan->fresh(), $take, 'internal', "GUAR-{$g->member->member_no}", null, $by->id);
            $g->increment('recovered_amount', $take);
            $this->sms->send($g->member->phone, "Dear {$g->member->first_name}, KES " . number_format($take, 2) . " has been recovered from your deposits towards loan {$loan->loan_no} "
                . "({$loan->member->full_name}) that you guaranteed, because the borrower defaulted. - " . sacco_name(), $g->member_id);
            $recovered += $take;
            if ($loan->fresh()->status !== 'disbursed') break;
        }
        AuditLog::record('guarantor_recovery', $loan, ['amount' => $recovered]);
        return $recovered;
    }

    /* ------------------------------------------------------------------
     |  Fines, waivers, recovery, write-off
     * ------------------------------------------------------------------ */

    /** Add a fine (bounced cheque, legal or collection costs...). Recognised as income when paid. */
    public function chargeFine(Loan $loan, float $amount, string $category, string $reason, User $by): void
    {
        $this->assertStatus($loan, ['disbursed', 'written_off']);
        if ($amount <= 0) throw new RuntimeException('Amount must be positive.');
        DB::transaction(function () use ($loan, $amount, $category, $reason, $by) {
            $row = $loan->schedules()->whereIn('status', ['pending', 'partial', 'overdue'])->orderBy('installment_no')->first()
                ?? $loan->schedules()->orderByDesc('installment_no')->first();
            $row->update(['penalty_due' => (float) $row->penalty_due + $amount, 'status' => $row->status === 'paid' ? 'partial' : $row->status]);
            $loan->increment('penalty_balance', $amount);
            $loan->charges()->create(['kind' => 'fine', 'category' => $category, 'amount' => $amount, 'reason' => $reason, 'user_id' => $by->id]);
            Transaction::create(['reference' => NumberGenerator::reference('FN'), 'member_id' => $loan->member_id, 'loan_id' => $loan->id,
                'type' => 'penalty', 'direction' => 'debit', 'amount' => $amount, 'channel' => 'internal', 'txn_date' => today(), 'posted_by' => $by->id,
                'description' => ucfirst(str_replace('_', ' ', $category)) . ": $reason"]);
            AuditLog::record('loan_fine', $loan, ['amount' => $amount, 'category' => $category]);
        });
    }

    /** Waive penalties and/or interest (never principal). Oldest installments first. */
    public function waive(Loan $loan, float $amount, string $what, string $reason, User $by): float
    {
        $this->assertStatus($loan, ['disbursed']);
        $parts = $what === 'both' ? ['penalty', 'interest'] : [$what];
        $left = $amount;
        DB::transaction(function () use ($loan, $parts, &$left, $reason, $by, $amount) {
            foreach ($parts as $part) {
                foreach ($loan->schedules()->whereIn('status', ['pending', 'partial', 'overdue'])->orderBy('installment_no')->get() as $s) {
                    $due = (float) $s->{"{$part}_due"} - (float) $s->{"{$part}_paid"};
                    $cut = min($due, $left);
                    if ($cut <= 0) continue;
                    $s->{"{$part}_due"} = round((float) $s->{"{$part}_due"} - $cut, 2);
                    if ($s->balance() <= 0.009) { $s->status = 'paid'; $s->paid_on = today(); }
                    $s->save();
                    $loan->{"{$part}_balance"} = round(max(0, (float) $loan->{"{$part}_balance"} - $cut), 2);
                    $left = round($left - $cut, 2);
                    if ($left <= 0) break 2;
                }
            }
            $waived = round($amount - $left, 2);
            if ($waived <= 0) throw new RuntimeException('Nothing to waive.');
            if ($loan->outstanding <= 0.009) { $loan->status = 'closed'; $loan->closed_on = today(); app(GuarantorService::class)->releaseAllForClosedLoan($loan); }
            $loan->save();
            $loan->charges()->create(['kind' => 'waiver', 'category' => implode('+', $parts), 'amount' => $waived, 'reason' => $reason, 'user_id' => $by->id]);
            AuditLog::record('loan_waiver', $loan, ['amount' => $waived, 'parts' => $parts]);
        });
        return round($amount - $left, 2);
    }

    public const RECOVERY_ACTIONS = [
        'call' => 'Phone call', 'sms' => 'SMS reminder', 'visit' => 'Visit / meeting', 'demand_letter' => 'Demand letter issued',
        'promise_to_pay' => 'Promise to pay', 'guarantor_notice' => 'Guarantors notified', 'guarantor_recovery' => 'Recovered from guarantors',
        'employer' => 'Employer contacted', 'auctioneer' => 'Instructed auctioneer', 'legal' => 'Legal action', 'note' => 'Note',
    ];

    public function addRecoveryAction(Loan $loan, string $action, ?string $notes, User $by, ?string $promiseDate = null, ?float $promiseAmount = null): void
    {
        $loan->recoveryActions()->create(['action' => $action, 'notes' => $notes, 'user_id' => $by->id,
            'promise_date' => $promiseDate, 'promise_amount' => $promiseAmount, 'promise_status' => $action === 'promise_to_pay' ? 'open' : null]);
        $stage = match ($action) { 'demand_letter' => 'demand', 'guarantor_notice', 'guarantor_recovery' => 'guarantors', 'auctioneer' => 'auctioneer', 'legal' => 'legal', default => $loan->recovery_stage ?? 'reminder' };
        $loan->forceFill(['recovery_stage' => $stage])->saveQuietly();
        if ($action === 'guarantor_notice') {
            foreach ($loan->guarantors()->where('status', 'accepted')->with('member')->get() as $g) {
                $this->sms->send($g->member->phone, "Dear {$g->member->first_name}, loan {$loan->loan_no} of {$loan->member->full_name} which you guaranteed is "
                    . $loan->daysInArrears() . " days in arrears (KES " . number_format($loan->arrears(), 2) . "). Please help follow up; the SACCO may recover from guarantors' deposits.", $g->member_id);
            }
        }
    }

    /** Daily: mark promises to pay as kept or broken. */
    public function reviewPromises(): array
    {
        $kept = 0; $broken = 0;
        foreach (\App\Models\LoanRecoveryAction::with('loan')->where('promise_status', 'open')->whereDate('promise_date', '<', today())->get() as $p) {
            $paid = (float) $p->loan->repayments()->where('paid_on', '>=', $p->created_at->toDateString())->sum('amount');
            $ok = $paid >= (float) $p->promise_amount;
            $p->update(['promise_status' => $ok ? 'kept' : 'broken']);
            $ok ? $kept++ : $broken++;
        }
        foreach (\App\Models\LoanRecoveryAction::with('loan.member')->where('promise_status', 'open')->whereDate('promise_date', today()->addDay())->get() as $p) {
            $this->sms->send($p->loan->member->phone, "Reminder: you promised to pay KES " . number_format($p->promise_amount, 2) . " on loan {$p->loan->loan_no} tomorrow. Paybill " . config('sacco.mpesa.paybill') . ", Acc {$p->loan->loan_no}.", $p->loan->member_id);
        }
        return ['kept' => $kept, 'broken' => $broken];
    }

    /** Write off the remaining principal as a bad debt. The member still owes it; later payments are bad-debt recoveries. */
    public function writeOff(Loan $loan, User $by, string $reason): void
    {
        $this->assertStatus($loan, ['disbursed']);
        $minDays = (int) Setting::get('write_off_min_days', 180);
        if ($loan->daysInArrears() < $minDays) throw new RuntimeException("Write-off needs at least $minDays days in arrears (currently {$loan->daysInArrears()}).");
        $principal = (float) $loan->principal_balance;
        DB::transaction(function () use ($loan, $by, $reason, $principal) {
            $acc = app(AccountingService::class);
            if ($principal > 0) {
                $acc->post(today(), "Write-off {$loan->loan_no} ({$loan->member->member_no}): $reason", [
                    [$acc->gl('provision_expense'), $principal, 0, 'Bad debt written off', $loan->member_id],
                    [$acc->gl('loans_receivable'), 0, $principal, $loan->loan_no, $loan->member_id],
                ], $loan);
            }
            $loan->update(['status' => 'written_off', 'written_off_on' => today(), 'written_off_amount' => $principal, 'written_off_by' => $by->id,
                'recovery_stage' => 'written_off', 'remarks' => trim($loan->remarks . "\nWritten off by {$by->name}: $reason")]);
            $loan->recoveryActions()->create(['action' => 'note', 'notes' => "Written off KES " . number_format($principal, 2) . ": $reason", 'user_id' => $by->id]);
            AuditLog::record('loan_written_off', $loan, ['principal' => $principal, 'reason' => $reason]);
        });
    }

    /** Money received on a written-off loan: Dr cash / Cr Bad debts recovered (income). */
    public function recoverWrittenOff(Loan $loan, float $amount, string $channel, ?string $ref, ?Carbon $date, ?int $postedBy): LoanRepayment
    {
        $date = $date ?? today();
        return DB::transaction(function () use ($loan, $amount, $channel, $ref, $date, $postedBy) {
            $owed = $loan->outstanding;
            $applied = min($amount, $owed);
            $p = min($applied, (float) $loan->principal_balance);
            $rest = $applied - $p;
            $i = min($rest, (float) $loan->interest_balance);
            $pen = min($rest - $i, (float) $loan->penalty_balance);
            $loan->principal_balance = round($loan->principal_balance - $p, 2);
            $loan->interest_balance = round($loan->interest_balance - $i, 2);
            $loan->penalty_balance = round($loan->penalty_balance - $pen, 2);
            $loan->recovered_after_write_off = round((float) $loan->recovered_after_write_off + $applied, 2);
            $loan->total_paid = round((float) $loan->total_paid + $applied, 2);
            if ($loan->outstanding <= 0.009) { $loan->recovery_stage = 'recovered'; $loan->closed_on = $date; }
            $loan->save();

            $refNo = NumberGenerator::reference('BR');
            // Created quietly: the normal repayment journal would credit Loans receivable, which was already written off.
            $rep = LoanRepayment::withoutEvents(fn () => $loan->repayments()->create([
                'reference' => $refNo, 'amount' => $amount, 'principal_paid' => $p, 'interest_paid' => $i, 'penalty_paid' => $pen,
                'overpayment' => $amount - $applied, 'channel' => $channel, 'external_ref' => $ref, 'paid_on' => $date, 'posted_by' => $postedBy ?? auth()->id(),
            ]));
            $acc = app(AccountingService::class);
            $income = \App\Models\GlAccount::where('system_key', 'bad_debt_recovered')->first() ?? $acc->gl('other_income');
            $acc->post($date, "Bad debt recovered {$loan->loan_no}", [
                [$acc->channelGl($channel), $amount, 0, $ref, $loan->member_id],
                [$income, 0, $applied, $loan->loan_no, $loan->member_id],
                [$acc->gl('clearing'), 0, $amount - $applied, 'Overpayment', $loan->member_id],
            ], $rep);
            Transaction::create(['reference' => $refNo, 'member_id' => $loan->member_id, 'loan_id' => $loan->id,
                'type' => 'loan_repayment', 'direction' => 'credit', 'amount' => $applied, 'balance_after' => $loan->outstanding,
                'channel' => $channel, 'external_ref' => $ref, 'txn_date' => $date, 'posted_by' => $postedBy ?? auth()->id(),
                'description' => "Recovery on written-off loan {$loan->loan_no}"]);
            if ($amount - $applied > 0) {
                $this->accounts->post($this->accounts->accountFor($loan->member, 'SAV'), 'deposit', 'credit', $amount - $applied,
                    ['reference' => $refNo, 'channel' => 'internal', 'date' => $date, 'description' => "Overpayment on {$loan->loan_no}"]);
            }
            AuditLog::record('bad_debt_recovered', $loan, ['amount' => $applied]);
            return $rep;
        });
    }

    private function assertStatus(Loan $loan, array $allowed): void
    {
        if (! in_array($loan->status, $allowed, true)) {
            throw new RuntimeException("Action not allowed while loan is '{$loan->status_label}'.");
        }
    }
}
