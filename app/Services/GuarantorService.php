<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Loan;
use App\Models\LoanGuarantor;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Guarantor rules in one place: registration, eligibility, limits, consent expiry,
 * release requests and automatic release when a loan is cleared.
 *
 * Settings (Administration → Settings):
 *   guarantor_registration_required  1 = only registered guarantors can be picked
 *   guarantor_deposit_multiple       limit = loanable deposits × this (default 1)
 *   guarantor_max_amount             absolute cap per guarantor, 0 = none
 *   guarantor_max_loans              max loans one member may guarantee at a time (0 = no limit)
 *   guarantor_min_months             membership months before one can guarantee
 *   guarantor_max_arrears_days       can't guarantee if own loan is more days late than this
 *   guarantor_allow_mutual           1 = allow A and B to guarantee each other
 *   guarantor_request_days           days a guarantor has to respond before the request lapses
 */
class GuarantorService
{
    public function __construct(private SmsService $sms) {}

    public function limit(Member $m): float
    {
        if ($m->max_guarantee_amount !== null) return (float) $m->max_guarantee_amount;
        $limit = $m->loanableDeposits() * (float) (setting('guarantor_deposit_multiple', 1) ?: 1);
        $cap = (float) setting('guarantor_max_amount', 0);
        return round($cap > 0 ? min($limit, $cap) : $limit, 2);
    }

    public function activeCount(Member $m, ?Loan $except = null): int
    {
        return $m->guarantees()->whereIn('status', ['pending', 'accepted'])
            ->whereHas('loan', fn ($q) => $q->whereIn('status', ['pending', 'guarantors_pending', 'appraised', 'approved', 'disbursed']))
            ->when($except, fn ($q) => $q->where('loan_id', '!=', $except->id))->count();
    }

    /**
     * Can $g guarantee $amount for $borrower? Returns eligible, issues (blocking) and warnings (for the officer to weigh).
     * Pass $ignore = the guarantee row being re-checked (e.g. on acceptance) so it isn't counted twice.
     */
    public function eligibility(Member $g, ?Member $borrower, float $amount, ?Loan $loan = null, ?LoanGuarantor $ignore = null): array
    {
        $issues = []; $warnings = [];
        $committed = $g->committedGuarantees() - ($ignore && $ignore->status === 'accepted' ? $ignore->exposure() : 0);
        $limit = $this->limit($g);
        $capacity = max(0, round($limit - $committed, 2));

        if ($borrower && $g->id === $borrower->id) $issues[] = 'A member cannot guarantee their own loan.';
        if ($g->status !== 'active') $issues[] = "{$g->member_no} is not an active member.";
        if ($g->guarantor_status === 'blocked') $issues[] = "{$g->member_no} is blocked from guaranteeing" . ($g->guarantor_note ? ": {$g->guarantor_note}" : '.');
        if ($g->guarantor_status === 'suspended') $issues[] = "{$g->member_no} is suspended as a guarantor" . ($g->guarantor_note ? ": {$g->guarantor_note}" : '.');
        if ((int) setting('guarantor_registration_required', 0) === 1 && ! $g->guarantor_registered_at)
            $issues[] = "{$g->member_no} has not registered as a guarantor.";

        $minMonths = (int) setting('guarantor_min_months', 0);
        if ($minMonths > 0 && $g->monthsOfMembership() < $minMonths) $issues[] = "{$g->member_no} has been a member for less than $minMonths months.";

        $maxDays = (int) setting('guarantor_max_arrears_days', 30);
        $late = $g->loans()->where('status', 'disbursed')->get()->filter(fn ($l) => $l->daysInArrears() > $maxDays);
        if ($late->count()) $issues[] = "{$g->member_no} has a loan more than $maxDays days in arrears.";
        if ($g->loans()->where('status', 'written_off')->exists()) $issues[] = "{$g->member_no} has a written-off loan.";

        $maxLoans = (int) setting('guarantor_max_loans', 0);
        $count = $this->activeCount($g, $loan);
        if ($maxLoans > 0 && ! $ignore && $count >= $maxLoans) $issues[] = "{$g->member_no} already guarantees $count loan(s), the maximum allowed is $maxLoans.";

        if ($amount > $capacity + 0.01) $issues[] = "{$g->member_no} can guarantee at most KES " . number_format($capacity, 2) . ' more.';
        if ($loan && $amount > (float) ($loan->amount_approved ?: $loan->amount_applied) + 0.01) $issues[] = 'Guarantee is more than the loan amount.';

        if ($borrower && $borrower->guarantees()->where('status', 'accepted')->whereHas('loan', fn ($q) => $q->where('member_id', $g->id)->whereIn('status', ['approved', 'disbursed']))->exists()) {
            (int) setting('guarantor_allow_mutual', 1) === 1
                ? $warnings[] = "Mutual guarantee: {$borrower->member_no} already guarantees a loan of {$g->member_no}."
                : $issues[] = "Mutual guarantees are not allowed: {$borrower->member_no} already guarantees {$g->member_no}.";
        }
        if ($capacity > 0 && $amount > 0.8 * $capacity) $warnings[] = "This uses " . round($amount / $capacity * 100) . "% of {$g->member_no}'s free guarantee capacity.";
        if ($g->loans()->where('status', 'disbursed')->get()->contains(fn ($l) => $l->daysInArrears() > 0)) $warnings[] = "{$g->member_no} is currently late on their own loan.";

        return ['eligible' => empty($issues), 'issues' => $issues, 'warnings' => $warnings, 'limit' => $limit,
            'committed' => round($committed, 2), 'capacity' => $capacity, 'active_count' => $count];
    }

    public function assertEligible(Member $g, ?Member $borrower, float $amount, ?Loan $loan = null, ?LoanGuarantor $ignore = null): void
    {
        $e = $this->eligibility($g, $borrower, $amount, $loan, $ignore);
        if (! $e['eligible']) throw new RuntimeException(implode(' ', $e['issues']));
    }

    /** New guarantee request (borrower or staff). */
    public function request(Loan $loan, Member $g, float $amount, string $by = 'borrower'): LoanGuarantor
    {
        if (! in_array($loan->status, ['guarantors_pending', 'pending', 'appraised', 'approved', 'disbursed']))
            throw new RuntimeException('Guarantors can no longer be added to this loan.');
        if ($loan->guarantors()->where('member_id', $g->id)->whereIn('status', ['pending', 'accepted'])->exists())
            throw new RuntimeException("{$g->member_no} is already a guarantor on this loan.");
        $this->assertEligible($g, $loan->member, $amount, $loan);

        $row = LoanGuarantor::updateOrCreate(['loan_id' => $loan->id, 'member_id' => $g->id], [
            'amount' => $amount, 'status' => 'pending', 'responded_at' => null, 'decline_reason' => null, 'consent_channel' => null,
            'released_at' => null, 'release_requested_at' => null, 'expires_at' => now()->addDays((int) setting('guarantor_request_days', 7) ?: 7),
        ]);
        $this->sms->send($g->phone, "Dear {$g->first_name}, {$loan->member->full_name} has asked you to guarantee loan {$loan->loan_no} for KES "
            . number_format($amount) . '. Log in to the member portal to accept or decline by ' . $row->expires_at->format('d/m') . '.', $g->id);
        AuditLog::record('guarantor_requested', $loan, ['member' => $g->id, 'amount' => $amount, 'by' => $by]);
        return $row;
    }

    /** Members opt in as guarantors (accept the guarantor terms). */
    public function register(Member $m, ?User $by = null): void
    {
        $m->update(['guarantor_registered_at' => now(), 'guarantor_status' => $m->guarantor_status === 'blocked' ? 'blocked' : 'eligible']);
        AuditLog::record('guarantor_registered', $m, ['by' => $by?->id ?? 'member']);
    }

    public function setStatus(Member $m, string $status, ?string $note, ?float $maxAmount, User $by): void
    {
        $m->update(['guarantor_status' => $status, 'guarantor_note' => $note, 'max_guarantee_amount' => $maxAmount]);
        AuditLog::record('guarantor_status', $m, ['status' => $status, 'max' => $maxAmount, 'by' => $by->id]);
    }

    /** Guarantor asks to be released (e.g. leaving the SACCO, needs their deposits for a loan). */
    public function requestRelease(LoanGuarantor $g, string $reason): void
    {
        if ($g->status !== 'accepted') throw new RuntimeException('Only active guarantees can be released.');
        if ($g->release_requested_at) throw new RuntimeException('You have already asked to be released from this loan.');
        $g->update(['release_requested_at' => now(), 'release_request_reason' => $reason]);
        $loan = $g->loan;
        $this->sms->send($loan->member->phone, "Dear {$loan->member->first_name}, {$g->member->full_name} has asked to be released as guarantor of loan {$loan->loan_no}. "
            . 'Please propose a replacement guarantor in the member portal.', $loan->member_id);
        AuditLog::record('guarantor_release_requested', $loan, ['member' => $g->member_id]);
    }

    /** Staff decision on a release request. Approving releases only if the loan stays covered. */
    public function decideRelease(LoanGuarantor $g, bool $approve, User $by, ?string $note = null): void
    {
        if (! $g->release_requested_at) throw new RuntimeException('No release request on this guarantee.');
        if ($approve) {
            $this->release($g, $by, 'Release request approved' . ($note ? ": $note" : ''));
        } else {
            $g->update(['release_requested_at' => null, 'release_request_reason' => null]);
            $this->sms->send($g->member->phone, "Dear {$g->member->first_name}, your request to be released from guaranteeing loan {$g->loan->loan_no} was not approved"
                . ($note ? ": $note" : '. The loan still needs your guarantee until a replacement is found.'), $g->member_id);
        }
    }

    public function release(LoanGuarantor $g, ?User $by, string $reason, bool $force = false): void
    {
        $loan = $g->loan;
        if (! $force && $loan->status === 'disbursed') {
            $remaining = $loan->guarantors()->where('status', 'accepted')->where('id', '!=', $g->id)->get()->sum(fn ($x) => $x->exposure());
            if ($remaining + $loan->member->freeGuaranteeCapacity() + 0.01 < (float) $loan->principal_balance)
                throw new RuntimeException('Releasing would leave the loan under-secured (other guarantees + borrower\'s deposits < principal balance). Replace the guarantor instead.');
        }
        $g->update(['status' => 'released', 'released_at' => now(), 'released_by' => $by?->id, 'release_reason' => $reason]);
        AuditLog::record('guarantor_released', $loan, ['member' => $g->member_id, 'reason' => $reason]);
        $this->sms->send($g->member->phone, "Dear {$g->member->first_name}, you have been released as guarantor of loan {$loan->loan_no} ({$loan->member->full_name}). - " . sacco_name(), $g->member_id);
    }

    /** Called when a loan is cleared: free every guarantor and tell them. */
    public function releaseAllForClosedLoan(Loan $loan): void
    {
        foreach ($loan->guarantors()->whereIn('status', ['accepted', 'pending'])->with('member')->get() as $g) {
            $was = $g->status;
            $g->update(['status' => $was === 'accepted' ? 'released' : 'withdrawn', 'released_at' => now(), 'release_reason' => 'Loan fully repaid']);
            if ($was === 'accepted') {
                $this->sms->send($g->member->phone, "Good news {$g->member->first_name}! Loan {$loan->loan_no} that you guaranteed has been fully repaid. Your guarantee of KES "
                    . number_format((float) $g->amount) . ' is released. - ' . sacco_name(), $g->member_id);
            }
        }
    }

    /** Withdraw open requests when a loan is rejected / cancelled. */
    public function withdrawPending(Loan $loan): void
    {
        $loan->guarantors()->whereIn('status', ['pending', 'accepted'])->update(['status' => 'withdrawn', 'released_at' => now(), 'release_reason' => "Loan {$loan->status}"]);
    }

    /** Daily: lapse unanswered requests, nudge guarantors 2 days before. */
    public function expireRequests(): array
    {
        $out = ['expired' => 0, 'nudged' => 0];
        foreach (LoanGuarantor::with('member', 'loan.member')->where('status', 'pending')->whereNotNull('expires_at')->get() as $g) {
            if (! $g->loan) continue;
            if ($g->expires_at->isPast()) {
                $g->update(['status' => 'expired', 'responded_at' => now()]);
                $out['expired']++;
                $this->sms->send($g->loan->member->phone, "Dear {$g->loan->member->first_name}, {$g->member->full_name} did not respond to your guarantor request for loan {$g->loan->loan_no}. "
                    . 'Please add another guarantor in the member portal.', $g->loan->member_id);
            } elseif ($g->expires_at->isSameDay(now()->addDays(2))) {
                $this->sms->send($g->member->phone, "Reminder: {$g->loan->member->full_name} is waiting for your answer to guarantee loan {$g->loan->loan_no} (KES "
                    . number_format((float) $g->amount) . '). The request lapses on ' . $g->expires_at->format('d/m') . '.', $g->member_id);
                $out['nudged']++;
            }
        }
        return $out;
    }

    /** Everything about one member as a guarantor, for the profile page and liability statement. */
    public function profile(Member $m): array
    {
        $rows = $m->guarantees()->with('loan.member', 'loan.product')->latest()->get();
        $active = $rows->where('status', 'accepted')->filter(fn ($g) => in_array($g->loan->status, ['pending', 'appraised', 'approved', 'disbursed', 'written_off']));
        return [
            'rows' => $rows, 'active' => $active, 'limit' => $this->limit($m), 'committed' => $m->committedGuarantees(),
            'capacity' => $m->freeGuaranteeCapacity(), 'deposits' => $m->loanableDeposits(),
            'guaranteed' => (float) $active->sum('amount'),
            'exposure' => (float) $active->sum(fn ($g) => $g->exposure()),
            'at_risk' => (float) $active->filter(fn ($g) => $g->loan->status === 'disbursed' && $g->loan->daysInArrears() > 30)->sum(fn ($g) => $g->exposure()),
            'recovered' => (float) $rows->sum('recovered_amount'),
            'pending' => $rows->where('status', 'pending')->count(),
        ];
    }
}
