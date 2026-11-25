<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\GlAccount;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Expenses post straight to the GL: Dr expense account / Cr cash-bank (paid) or Accounts payable (unpaid bill). */
class ExpenseController extends Controller
{
    public function __construct(private AccountingService $acc) {}

    public function index(Request $request)
    {
        $expenses = Expense::with('recorder', 'glAccount', 'paidFrom')
            ->when($request->account, fn ($q, $a) => $q->where('gl_account_id', $a))
            ->when($request->unpaid, fn ($q) => $q->where('is_paid', false))
            ->latest('spent_on')->paginate(30)->withQueryString();
        return view('expenses.index', [
            'expenses' => $expenses,
            'expenseAccounts' => GlAccount::where('type', 'expense')->where('is_active', true)->orderBy('code')->get(),
            'cashAccounts' => GlAccount::where('is_cash', true)->orderBy('code')->get(),
            'payables' => (float) Expense::where('is_paid', false)->sum('amount'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'gl_account_id' => 'required|exists:gl_accounts,id', 'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:1', 'spent_on' => 'required|date',
            'paid_from_gl_id' => 'nullable|required_if:is_paid,1|exists:gl_accounts,id',
            'payee' => 'nullable|string|max:120', 'reference' => 'nullable|string|max:60',
        ]);
        $data['is_paid'] = $request->boolean('is_paid');
        $data['category'] = GlAccount::find($data['gl_account_id'])->name;
        if (! $data['is_paid']) $data['paid_from_gl_id'] = null;

        try {
            DB::transaction(function () use ($data, $request) {
                $e = Expense::create($data + ['recorded_by' => $request->user()->id]);
                $this->acc->post($e->spent_on, "Expense: {$e->description}" . ($e->payee ? " ({$e->payee})" : ''), [
                    [$e->gl_account_id, (float) $e->amount, 0, $e->reference],
                    [$e->is_paid ? $e->paid_from_gl_id : $this->acc->gl('payables')->id, 0, (float) $e->amount, $e->payee],
                ], $e);
            });
        } catch (RuntimeException $ex) {
            return back()->withInput()->with('error', $ex->getMessage());
        }
        return back()->with('success', $data['is_paid'] ? 'Expense recorded.' : 'Bill recorded in accounts payable.');
    }

    /** Pay an outstanding bill: Dr Accounts payable / Cr cash-bank. */
    public function pay(Request $request, Expense $expense)
    {
        $request->validate(['paid_from_gl_id' => 'required|exists:gl_accounts,id', 'paid_on' => 'required|date']);
        if ($expense->is_paid) return back()->with('error', 'Already paid.');
        try {
            DB::transaction(function () use ($expense, $request) {
                $this->acc->post($request->paid_on, "Payment of bill: {$expense->description}", [
                    [$this->acc->gl('payables'), (float) $expense->amount, 0, $expense->payee],
                    [(int) $request->paid_from_gl_id, 0, (float) $expense->amount, $expense->reference],
                ], $expense);
                $expense->update(['is_paid' => true, 'paid_from_gl_id' => $request->paid_from_gl_id]);
            });
        } catch (RuntimeException $ex) {
            return back()->with('error', $ex->getMessage());
        }
        return back()->with('success', 'Bill paid.');
    }
}
