<?php

namespace App\Http\Controllers;

use App\Models\AccountType;
use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\GlAccount;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Loan;
use App\Services\AccountingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class AccountingController extends Controller
{
    public function __construct(private AccountingService $acc) {}

    /* ---------- Chart of accounts ---------- */

    public function accounts()
    {
        $t = $this->acc->totals();
        $accounts = GlAccount::with('parent')->orderBy('code')->get()->each(function ($a) use ($t) {
            $dr = (float) ($t[$a->id]->dr ?? 0); $cr = (float) ($t[$a->id]->cr ?? 0);
            $a->bal = $a->isDebitNormal() ? $dr - $cr : $cr - $dr;
        });
        return view('accounting.accounts', compact('accounts'));
    }

    public function storeAccount(Request $r)
    {
        GlAccount::create($this->validatedAccount($r));
        return back()->with('success', 'Account added.');
    }

    public function updateAccount(Request $r, GlAccount $account)
    {
        $account->update($this->validatedAccount($r, $account));
        return back()->with('success', 'Account updated.');
    }

    private function validatedAccount(Request $r, ?GlAccount $a = null): array
    {
        $d = $r->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('gl_accounts')->ignore($a)],
            'name' => 'required|string|max:120',
            'type' => 'required|in:asset,liability,equity,income,expense',
            'parent_id' => 'nullable|exists:gl_accounts,id',
        ]);
        $d['is_cash'] = $r->boolean('is_cash');
        $d['is_active'] = $r->boolean('is_active', true);
        return $d;
    }

    public function ledger(Request $r, GlAccount $account)
    {
        $from = $r->input('from', today()->startOfMonth()->toDateString());
        $to = $r->input('to', today()->toDateString());
        $opening = $account->balance(null, Carbon::parse($from)->subDay());
        $lines = JournalLine::with('entry', 'member')->where('gl_account_id', $account->id)
            ->whereHas('entry', fn ($q) => $q->where('status', '!=', 'draft')->whereBetween('entry_date', [$from, $to]))
            ->get()->sortBy(fn ($l) => $l->entry->entry_date->format('Ymd') . str_pad($l->id, 10, '0', STR_PAD_LEFT));
        return view('accounting.ledger', compact('account', 'lines', 'opening', 'from', 'to'));
    }

    /* ---------- Journals ---------- */

    public function journals(Request $r)
    {
        $entries = JournalEntry::with('lines', 'creator')
            ->when($r->status, fn ($q, $s) => $q->where('status', $s))
            ->when($r->q, fn ($q, $s) => $q->where(fn ($w) => $w->where('entry_no', 'like', "%$s%")->orWhere('description', 'like', "%$s%")))
            ->when($r->manual, fn ($q) => $q->whereNull('source_type'))
            ->latest('entry_date')->latest('id')->paginate(30)->withQueryString();
        return view('accounting.journals', compact('entries'));
    }

    public function createJournal()
    {
        return view('accounting.journal-form', ['accounts' => GlAccount::where('is_active', true)->orderBy('code')->get()]);
    }

    public function storeJournal(Request $r)
    {
        $d = $r->validate([
            'entry_date' => 'required|date', 'description' => 'required|string|max:255',
            'lines' => 'required|array|min:2', 'lines.*.gl_account_id' => 'required|exists:gl_accounts,id',
            'lines.*.debit' => 'nullable|numeric|min:0', 'lines.*.credit' => 'nullable|numeric|min:0', 'lines.*.memo' => 'nullable|string|max:255',
        ]);
        $lines = collect($d['lines'])->map(fn ($l) => [(int) $l['gl_account_id'], (float) ($l['debit'] ?? 0), (float) ($l['credit'] ?? 0), $l['memo'] ?? null])->all();
        try {
            $e = $this->acc->post($d['entry_date'], $d['description'], $lines, null, true);
        } catch (RuntimeException $ex) {
            return back()->withInput()->with('error', $ex->getMessage());
        }
        AuditLog::record('journal_drafted', $e);
        return redirect()->route('accounting.journals.show', $e)->with('success', 'Journal saved as draft. Another user must post it.');
    }

    public function showJournal(JournalEntry $entry)
    {
        $entry->load('lines.account', 'lines.member', 'creator', 'poster');
        return view('accounting.journal-show', compact('entry'));
    }

    public function postJournal(JournalEntry $entry)
    {
        try { $this->acc->approveDraft($entry); } catch (RuntimeException $e) { return back()->with('error', $e->getMessage()); }
        return back()->with('success', 'Journal posted.');
    }

    public function reverseJournal(Request $r, JournalEntry $entry)
    {
        $r->validate(['reason' => 'required|string|max:200']);
        if ($entry->source_type) return back()->with('error', 'System journals are reversed from their source (e.g. reverse the member transaction).');
        try { $this->acc->reverse($entry, $r->reason); } catch (RuntimeException $e) { return back()->with('error', $e->getMessage()); }
        return back()->with('success', 'Journal reversed.');
    }

    /* ---------- Financial statements ---------- */

    public function trialBalance(Request $r)
    {
        $asAt = $r->input('as_at', today()->toDateString());
        return view('accounting.trial-balance', ['tb' => $this->acc->trialBalance($asAt), 'asAt' => $asAt]);
    }

    public function profitLoss(Request $r)
    {
        $from = $r->input('from', today()->startOfYear()->toDateString());
        $to = $r->input('to', today()->toDateString());
        return view('accounting.profit-loss', ['pl' => $this->acc->profitAndLoss($from, $to), 'from' => $from, 'to' => $to]);
    }

    public function balanceSheet(Request $r)
    {
        $asAt = $r->input('as_at', today()->toDateString());
        return view('accounting.balance-sheet', ['bs' => $this->acc->balanceSheet($asAt), 'asAt' => $asAt]);
    }

    public function cashFlow(Request $r)
    {
        $from = $r->input('from', today()->startOfYear()->toDateString());
        $to = $r->input('to', today()->toDateString());
        return view('accounting.cash-flow', ['cf' => $this->acc->cashFlow($from, $to), 'from' => $from, 'to' => $to]);
    }

    /** Key prudential indicators (SASRA-style) computed from the GL and loan book. */
    public function ratios(Request $r)
    {
        $asAt = $r->input('as_at', today()->toDateString());
        $bs = $this->acc->balanceSheet($asAt);
        $sum = fn ($keys) => collect($keys)->sum(fn ($k) => optional(GlAccount::where('system_key', $k)->first())->balance(null, $asAt) ?? 0);
        $cash = GlAccount::where('is_cash', true)->get()->sum(fn ($a) => $a->balance(null, $asAt));
        $deposits = AccountType::whereNotNull('gl_account_id')->where('code', '!=', 'SHR')->get()->sum(fn ($t) => $t->glAccount->balance(null, $asAt)) + $sum(['fixed_deposits']);
        $shares = $sum(['acct_SHR']);
        $institutional = $sum(['retained_earnings']) + $bs['current_surplus'] + $sum(['statutory_reserve']);
        $core = $shares + $institutional;
        $loans = $sum(['loans_receivable']);
        $par30 = Loan::where('status', 'disbursed')->get()->filter(fn ($l) => $l->daysInArrears() > 30)->sum('principal_balance');
        $totalAssets = max(1, $bs['total_assets']);

        $ratios = [
            ['Core capital / total assets', $core / $totalAssets * 100, '≥ 10%'],
            ['Institutional capital / total assets', $institutional / $totalAssets * 100, '≥ 8%'],
            ['Core capital / total deposits', $deposits > 0 ? $core / $deposits * 100 : 0, '≥ 8%'],
            ['Liquidity (cash & bank / deposits)', $deposits > 0 ? $cash / $deposits * 100 : 0, '≥ 15%'],
            ['Loans / deposits', $deposits > 0 ? $loans / $deposits * 100 : 0, '≤ 100% (internal)'],
            ['Portfolio at risk > 30 days', $loans > 0 ? $par30 / $loans * 100 : 0, '≤ 5%'],
        ];
        return view('accounting.ratios', compact('ratios', 'asAt', 'core', 'institutional', 'deposits', 'cash', 'loans', 'totalAssets'));
    }

    /* ---------- Banks & reconciliation ---------- */

    public function banks()
    {
        $banks = BankAccount::with('glAccount')->get();
        $cashAccounts = GlAccount::where('is_cash', true)->orderBy('code')->get();
        return view('accounting.banks', compact('banks', 'cashAccounts'));
    }

    public function storeBank(Request $r)
    {
        BankAccount::create($r->validate(['name' => 'required|string|max:120', 'bank' => 'nullable|string|max:120',
            'account_number' => 'nullable|string|max:40', 'gl_account_id' => 'required|exists:gl_accounts,id']));
        return back()->with('success', 'Bank account added.');
    }

    public function reconcile(Request $r, BankAccount $bank)
    {
        $rec = BankReconciliation::where('bank_account_id', $bank->id)->where('status', 'open')->latest()->first();
        if (! $rec && $r->filled('statement_date')) {
            $d = $r->validate(['statement_date' => 'required|date', 'statement_balance' => 'required|numeric']);
            $rec = BankReconciliation::create($d + ['bank_account_id' => $bank->id, 'done_by' => auth()->id()]);
        }
        $lines = $rec ? JournalLine::with('entry')->where('gl_account_id', $bank->gl_account_id)
            ->where(fn ($q) => $q->whereNull('reconciled_at')->orWhere('reconciliation_id', $rec->id))
            ->whereHas('entry', fn ($q) => $q->where('status', '!=', 'draft')->whereDate('entry_date', '<=', $rec->statement_date))
            ->get()->sortBy(fn ($l) => $l->entry->entry_date) : collect();
        $clearedBefore = (float) JournalLine::where('gl_account_id', $bank->gl_account_id)->whereNotNull('reconciled_at')
            ->when($rec, fn ($q) => $q->where('reconciliation_id', '!=', $rec->id))->selectRaw('COALESCE(SUM(debit - credit),0) b')->value('b');
        $history = BankReconciliation::where('bank_account_id', $bank->id)->latest()->take(10)->get();
        return view('accounting.reconcile', compact('bank', 'rec', 'lines', 'clearedBefore', 'history'));
    }

    public function saveReconciliation(Request $r, BankReconciliation $rec)
    {
        abort_if($rec->status === 'completed', 403);
        $ids = array_map('intval', $r->input('cleared', []));
        $bankGl = $rec->bankAccount->gl_account_id;
        JournalLine::where('reconciliation_id', $rec->id)->whereNotIn('id', $ids)->update(['reconciled_at' => null, 'reconciliation_id' => null]);
        JournalLine::whereIn('id', $ids)->where('gl_account_id', $bankGl)->update(['reconciled_at' => now(), 'reconciliation_id' => $rec->id]);

        $cleared = (float) JournalLine::where('gl_account_id', $bankGl)->whereNotNull('reconciled_at')->selectRaw('COALESCE(SUM(debit - credit),0) b')->value('b');
        $rec->update(['cleared_balance' => $cleared, 'difference' => round((float) $rec->statement_balance - $cleared, 2)]);

        if ($r->boolean('complete')) {
            if (abs($rec->difference) > 0.009) return back()->with('error', 'Difference must be zero to complete. Post missing bank charges/receipts as journals first.');
            $rec->update(['status' => 'completed']);
            AuditLog::record('bank_reconciled', $rec);
            return back()->with('success', 'Reconciliation completed.');
        }
        return back()->with('success', 'Saved. Difference: ' . number_format($rec->difference, 2));
    }

    public function closeYear(Request $r)
    {
        $r->validate(['year' => 'required|integer|min:2000|max:' . now()->year]);
        try { $this->acc->closeYear((int) $r->year); } catch (RuntimeException $e) { return back()->with('error', $e->getMessage()); }
        return back()->with('success', "Financial year {$r->year} closed and books locked.");
    }
}
