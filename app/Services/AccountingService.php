<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\GlAccount;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\LoanRepayment;
use App\Models\Setting;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Automated double-entry accounting.
 *
 * Every member-ledger Transaction and every LoanRepayment is turned into a balanced journal
 * (via model observers) so the general ledger is always in step with member accounts.
 * Interest and penalties are recognised as income when received (cash basis), which is how
 * most Kenyan SACCOs run their books; loans receivable carries principal only.
 */
class AccountingService
{
    /** Map payment channel -> GL system key. */
    public const CHANNEL_ACCOUNTS = [
        'cash'     => 'cash',
        'mpesa'    => 'mpesa',
        'bank'     => 'bank',
        'cheque'   => 'bank',
        'payroll'  => 'checkoff_receivable',
        'internal' => 'clearing',
        'opening'  => 'opening_balances', // go-live balance migration
    ];

    public function gl(string $key): GlAccount
    {
        return GlAccount::bySystemKey($key);
    }

    public function channelGl(?string $channel): GlAccount
    {
        return $this->gl(self::CHANNEL_ACCOUNTS[$channel ?? 'cash'] ?? 'cash');
    }

    /**
     * @param array<int, array{0: GlAccount|int, 1: float, 2: float, 3?: string|null, 4?: int|null}> $lines [account, debit, credit, memo, member_id]
     */
    public function post($date, string $description, array $lines, $source = null, bool $draft = false, bool $closing = false): JournalEntry
    {
        $date = Carbon::parse($date);
        $this->assertOpen($date);

        $lines = array_values(array_filter($lines, fn ($l) => round((float) $l[1], 2) != 0 || round((float) $l[2], 2) != 0));
        $dr = round(array_sum(array_column($lines, 1)), 2);
        $cr = round(array_sum(array_column($lines, 2)), 2);
        if (! $lines) throw new RuntimeException('Journal has no lines.');
        if (abs($dr - $cr) > 0.009) throw new RuntimeException("Journal not balanced (Dr $dr / Cr $cr).");

        return DB::transaction(function () use ($date, $description, $lines, $source, $draft, $closing) {
            $entry = JournalEntry::create([
                'entry_no'    => $this->nextNo(),
                'entry_date'  => $date->toDateString(),
                'description' => mb_substr($description, 0, 255),
                'branch_id'   => $source?->getAttribute('branch_id') ?? auth()->user()?->branch_id,
                'source_type' => $source ? $source::class : null,
                'source_id'   => $source?->getKey(),
                'status'      => $draft ? 'draft' : 'posted',
                'is_closing'  => $closing,
                'created_by'  => auth()->id(),
                'posted_by'   => $draft ? null : auth()->id(),
                'posted_at'   => $draft ? null : now(),
            ]);
            foreach ($lines as $l) {
                $entry->lines()->create([
                    'gl_account_id' => $l[0] instanceof GlAccount ? $l[0]->id : $l[0],
                    'debit'  => round((float) $l[1], 2),
                    'credit' => round((float) $l[2], 2),
                    'memo'   => $l[3] ?? null,
                    'member_id' => $l[4] ?? null,
                ]);
            }
            return $entry;
        });
    }

    /** Maker-checker: a draft manual journal is posted by a different user. */
    public function approveDraft(JournalEntry $entry): void
    {
        if ($entry->status !== 'draft') throw new RuntimeException('Only draft journals can be posted.');
        if ($entry->created_by === auth()->id() && ! auth()->user()?->hasRole('admin'))
            throw new RuntimeException('Maker-checker: another user must post this journal.');
        $this->assertOpen($entry->entry_date);
        $entry->update(['status' => 'posted', 'posted_by' => auth()->id(), 'posted_at' => now()]);
        AuditLog::record('journal_posted', $entry);
    }

    public function reverse(JournalEntry $entry, ?string $reason = null, $date = null): JournalEntry
    {
        if ($entry->status !== 'posted') throw new RuntimeException('Only posted journals can be reversed.');
        $rev = $this->post($date ?? today(), "Reversal of {$entry->entry_no}" . ($reason ? ": $reason" : ''),
            $entry->lines->map(fn ($l) => [$l->gl_account_id, (float) $l->credit, (float) $l->debit, $l->memo, $l->member_id])->all());
        $rev->update(['reversal_of' => $entry->id]);
        $entry->update(['status' => 'reversed']);
        return $rev;
    }

    public function reverseSource($source, string $reason): void
    {
        JournalEntry::where('source_type', $source::class)->where('source_id', $source->getKey())
            ->where('status', 'posted')->get()->each(fn ($e) => $this->reverse($e, $reason));
    }

    /* ------------------------------------------------------------------
     |  Automatic postings
     * ------------------------------------------------------------------ */

    public function fromTransaction(Transaction $tx): void
    {
        // These are posted elsewhere (repayment split, dividend batch) or are memo-only.
        if (in_array($tx->type, ['loan_repayment', 'penalty', 'reversal'], true)) return;
        if ((float) $tx->amount <= 0) return;

        $memberGl = $tx->account?->type?->gl_account_id ?: $this->gl('clearing')->id;
        $amt = (float) $tx->amount;
        $m = $tx->member_id;
        $desc = $tx->description ?: str_replace('_', ' ', $tx->type);

        $lines = match (true) {
            $tx->type === 'deposit' =>
                [[$this->channelGl($tx->channel), $amt, 0, $desc, $m], [$memberGl, 0, $amt, $desc, $m]],
            $tx->type === 'withdrawal' =>
                [[$memberGl, $amt, 0, $desc, $m], [$this->channelGl($tx->channel), 0, $amt, $desc, $m]],
            $tx->type === 'transfer_in' =>
                [[$this->gl('clearing'), $amt, 0, $desc, $m], [$memberGl, 0, $amt, $desc, $m]],
            $tx->type === 'transfer_out' =>
                [[$memberGl, $amt, 0, $desc, $m], [$this->gl('clearing'), 0, $amt, $desc, $m]],
            $tx->type === 'dividend' =>
                [[$this->gl('clearing'), $amt, 0, $desc, $m], [$memberGl, 0, $amt, $desc, $m]],
            $tx->type === 'interest' =>
                [[$this->gl('savings_interest_expense'), $amt, 0, $desc, $m], [$memberGl, 0, $amt, $desc, $m]],
            $tx->type === 'loan_disbursement' && $tx->member_account_id !== null =>
                [[$this->gl('loans_receivable'), $amt, 0, $desc, $m], [$memberGl, 0, $amt, $desc, $m]],
            $tx->type === 'loan_disbursement' =>
                [[$this->gl('loans_receivable'), $amt, 0, $desc, $m], [$this->channelGl($tx->channel), 0, $amt, $desc, $m]],
            $tx->type === 'fee' && $tx->loan_id !== null =>
                [[$this->gl('loans_receivable'), $amt, 0, $desc, $m],
                 [$this->gl($tx->external_ref === 'INSURANCE' ? 'insurance_payable' : 'processing_fee_income'), 0, $amt, $desc, $m]],
            $tx->type === 'fee' && $tx->member_account_id !== null =>
                [[$memberGl, $amt, 0, $desc, $m], [$this->gl('fee_income'), 0, $amt, $desc, $m]],
            $tx->type === 'fee' =>
                [[$this->channelGl($tx->channel), $amt, 0, $desc, $m], [$this->gl('registration_fee_income'), 0, $amt, $desc, $m]],
            // Welfare: from a member account (deduction) or paid in cash/M-Pesa, into the welfare fund; payouts the other way
            $tx->type === 'welfare' && $tx->member_account_id !== null =>
                [[$memberGl, $amt, 0, $desc, $m], [$this->gl('welfare_fund'), 0, $amt, $desc, $m]],
            $tx->type === 'welfare' =>
                [[$this->channelGl($tx->channel), $amt, 0, $desc, $m], [$this->gl('welfare_fund'), 0, $amt, $desc, $m]],
            $tx->type === 'welfare_payout' && $tx->member_account_id !== null =>
                [[$this->gl('welfare_fund'), $amt, 0, $desc, $m], [$memberGl, 0, $amt, $desc, $m]],
            $tx->type === 'welfare_payout' =>
                [[$this->gl('welfare_fund'), $amt, 0, $desc, $m], [$this->channelGl($tx->channel), 0, $amt, $desc, $m]],
            default => null,
        };

        if ($lines) $this->post($tx->txn_date, "{$tx->reference} · $desc", $lines, $tx);
    }

    public function fromRepayment(LoanRepayment $r): void
    {
        $loan = $r->loan;
        $m = $loan->member_id;
        $d = "Repayment {$loan->loan_no}";
        $this->post($r->paid_on, "{$r->reference} · $d", [
            [$this->channelGl($r->channel), (float) $r->amount, 0, $d, $m],
            [$this->gl('loans_receivable'), 0, (float) $r->principal_paid, 'Principal', $m],
            [$this->gl('interest_income'), 0, (float) $r->interest_paid, 'Interest', $m],
            [$this->gl('penalty_income'), 0, (float) $r->penalty_paid, 'Penalty', $m],
            [$this->gl('clearing'), 0, (float) $r->overpayment, 'Overpayment to savings', $m],
        ], $r);
    }

    /* ------------------------------------------------------------------
     |  Reporting
     * ------------------------------------------------------------------ */

    /** Per-account debit & credit totals for posted journals in a date window. */
    public function totals($from = null, $to = null)
    {
        return JournalLine::query()
            ->join('journal_entries as e', 'e.id', '=', 'journal_lines.journal_entry_id')
            ->where('e.status', '!=', 'draft')
            ->when($from, fn ($q) => $q->whereDate('e.entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('e.entry_date', '<=', $to))
            ->groupBy('journal_lines.gl_account_id')
            ->selectRaw('journal_lines.gl_account_id, SUM(debit) dr, SUM(credit) cr')
            ->get()->keyBy('gl_account_id');
    }

    public function trialBalance($asAt): array
    {
        $t = $this->totals(null, $asAt);
        $rows = GlAccount::orderBy('code')->get()->map(function ($a) use ($t) {
            $net = (float) ($t[$a->id]->dr ?? 0) - (float) ($t[$a->id]->cr ?? 0);
            return ['account' => $a, 'debit' => $net > 0 ? $net : 0, 'credit' => $net < 0 ? -$net : 0];
        })->filter(fn ($r) => $r['debit'] || $r['credit'])->values();
        return ['rows' => $rows, 'debit' => $rows->sum('debit'), 'credit' => $rows->sum('credit')];
    }

    public function profitAndLoss($from, $to): array
    {
        $t = $this->totals($from, $to);
        $closing = $this->closingTotals($from, $to);
        $calc = function ($type) use ($t, $closing) {
            return GlAccount::where('type', $type)->orderBy('code')->get()->map(function ($a) use ($t, $closing, $type) {
                $dr = (float) ($t[$a->id]->dr ?? 0) - (float) ($closing[$a->id]->dr ?? 0);
                $cr = (float) ($t[$a->id]->cr ?? 0) - (float) ($closing[$a->id]->cr ?? 0);
                return ['account' => $a, 'amount' => round($type === 'income' ? $cr - $dr : $dr - $cr, 2)];
            })->filter(fn ($r) => $r['amount'] != 0)->values();
        };
        $income = $calc('income');
        $expense = $calc('expense');
        return ['income' => $income, 'expenses' => $expense,
                'total_income' => $income->sum('amount'), 'total_expenses' => $expense->sum('amount'),
                'surplus' => $income->sum('amount') - $expense->sum('amount')];
    }

    public function balanceSheet($asAt): array
    {
        $t = $this->totals(null, $asAt);
        $section = fn ($type) => GlAccount::where('type', $type)->orderBy('code')->get()->map(function ($a) use ($t, $type) {
            $dr = (float) ($t[$a->id]->dr ?? 0); $cr = (float) ($t[$a->id]->cr ?? 0);
            return ['account' => $a, 'amount' => round($type === 'asset' ? $dr - $cr : $cr - $dr, 2)];
        })->filter(fn ($r) => $r['amount'] != 0)->values();

        $assets = $section('asset'); $liabilities = $section('liability'); $equity = $section('equity');
        // Unclosed surplus (income - expenses not yet transferred to retained earnings)
        $inc = $section('income')->sum('amount');
        $exp = GlAccount::where('type', 'expense')->get()->sum(fn ($a) => (float) ($t[$a->id]->dr ?? 0) - (float) ($t[$a->id]->cr ?? 0));
        $currentSurplus = round($inc - $exp, 2);

        return ['assets' => $assets, 'liabilities' => $liabilities, 'equity' => $equity,
                'current_surplus' => $currentSurplus, 'total_assets' => $assets->sum('amount'),
                'total_le' => $liabilities->sum('amount') + $equity->sum('amount') + $currentSurplus];
    }

    /** Direct-method cash flow: movements on cash GLs classified by the other side of each journal. */
    public function cashFlow($from, $to): array
    {
        $cashIds = GlAccount::where('is_cash', true)->pluck('id');
        $financingKeys = ['acct_SHR', 'acct_DEP', 'acct_SAV', 'fixed_deposits'];
        $financingIds = GlAccount::whereIn('system_key', $financingKeys)->orWhereIn('id', \App\Models\AccountType::pluck('gl_account_id')->filter())->pluck('id');
        $investingIds = GlAccount::whereIn('system_key', ['fixed_assets', 'accumulated_depreciation'])->pluck('id');

        $groups = ['Operating' => [], 'Investing' => [], 'Financing' => []];
        $entries = JournalEntry::with('lines.account')->where('status', '!=', 'draft')
            ->whereBetween('entry_date', [$from, $to])
            ->whereHas('lines', fn ($q) => $q->whereIn('gl_account_id', $cashIds))->get();

        foreach ($entries as $e) {
            $cashNet = $e->lines->whereIn('gl_account_id', $cashIds)->sum(fn ($l) => (float) $l->debit - (float) $l->credit);
            if (abs($cashNet) < 0.01) continue; // transfers between cash accounts
            $others = $e->lines->whereNotIn('gl_account_id', $cashIds);
            $total = $others->sum(fn ($l) => abs((float) $l->debit - (float) $l->credit)) ?: 1;
            foreach ($others as $l) {
                $share = $cashNet * abs((float) $l->debit - (float) $l->credit) / $total;
                $g = $financingIds->contains($l->gl_account_id) ? 'Financing' : ($investingIds->contains($l->gl_account_id) ? 'Investing' : 'Operating');
                $groups[$g][$l->account->name] = ($groups[$g][$l->account->name] ?? 0) + $share;
            }
        }
        $opening = GlAccount::whereIn('id', $cashIds)->get()->sum(fn ($a) => $a->balance(null, Carbon::parse($from)->subDay()));
        $net = array_sum(array_map('array_sum', $groups));
        return ['groups' => $groups, 'opening' => $opening, 'net' => $net, 'closing' => $opening + $net];
    }

    /** Year-end: transfer income & expenses to retained earnings and lock the books. */
    public function closeYear(int $year): JournalEntry
    {
        $from = "$year-01-01"; $to = "$year-12-31";
        if (JournalEntry::where('is_closing', true)->whereYear('entry_date', $year)->where('status', 'posted')->exists())
            throw new RuntimeException("$year is already closed.");

        $t = $this->totals($from, $to);
        $lines = [];
        $surplus = 0;
        foreach (GlAccount::whereIn('type', ['income', 'expense'])->get() as $a) {
            $net = (float) ($t[$a->id]->dr ?? 0) - (float) ($t[$a->id]->cr ?? 0);
            if (abs($net) < 0.01) continue;
            $lines[] = [$a, $net < 0 ? -$net : 0, $net > 0 ? $net : 0, "Close $year"];
            $surplus -= $net;
        }
        $lines[] = [$this->gl('retained_earnings'), $surplus < 0 ? -$surplus : 0, $surplus > 0 ? $surplus : 0, "Surplus $year"];
        $entry = $this->post($to, "Year-end closing entry $year", $lines, null, false, true);
        Setting::put('books_locked_until', $to);
        AuditLog::record('year_closed', $entry, ['year' => $year, 'surplus' => $surplus]);
        return $entry;
    }

    private function closingTotals($from, $to)
    {
        return JournalLine::query()->join('journal_entries as e', 'e.id', '=', 'journal_lines.journal_entry_id')
            ->where('e.is_closing', true)->where('e.status', 'posted')
            ->whereBetween('e.entry_date', [$from, $to])
            ->groupBy('journal_lines.gl_account_id')
            ->selectRaw('journal_lines.gl_account_id, SUM(debit) dr, SUM(credit) cr')->get()->keyBy('gl_account_id');
    }

    private function assertOpen($date): void
    {
        $lock = Setting::get('books_locked_until');
        if ($lock && Carbon::parse($date)->lte(Carbon::parse($lock))) {
            throw new RuntimeException('Books are closed up to ' . Carbon::parse($lock)->format('d M Y') . '. Post on a later date.');
        }
    }

    private function nextNo(): string
    {
        $last = JournalEntry::lockForUpdate()->orderByDesc('id')->value('entry_no');
        return 'JE' . str_pad((string) ((int) substr((string) $last, 2) + 1), 7, '0', STR_PAD_LEFT);
    }
}
