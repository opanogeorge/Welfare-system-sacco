<?php

namespace App\Http\Controllers;

use App\Models\AccountType;
use App\Models\Member;
use App\Models\Transaction;
use App\Services\AccountService;
use App\Services\SmsService;
use Illuminate\Http\Request;
use RuntimeException;

class TransactionController extends Controller
{
    public function __construct(private AccountService $accounts, private SmsService $sms) {}

    public function index(Request $request)
    {
        $q = Transaction::with('member', 'account.type', 'poster')->visibleTo($request->user(), $request->branch)
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->when($request->channel, fn ($q, $c) => $q->where('channel', $c))
            ->when($request->from, fn ($q, $d) => $q->whereDate('txn_date', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->whereDate('txn_date', '<=', $d))
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w->where('reference', 'like', "%$s%")
                ->orWhere('external_ref', 'like', "%$s%")
                ->orWhereHas('member', fn ($m) => $m->search($s))));

        $totals = (clone $q)->selectRaw("SUM(CASE WHEN direction='credit' THEN amount ELSE 0 END) cr, SUM(CASE WHEN direction='debit' THEN amount ELSE 0 END) dr")->first();
        $transactions = $q->latest('txn_date')->latest('id')->paginate(30)->withQueryString();
        return view('transactions.index', compact('transactions', 'totals'));
    }

    public function create(Request $request)
    {
        $member = $request->member ? Member::with('accounts.type')->find($request->member) : null;
        $types = AccountType::where('is_active', true)->get();
        return view('transactions.create', compact('member', 'types'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'member_id' => 'required|exists:members,id',
            'action' => 'required|in:deposit,withdrawal',
            'account' => 'required|exists:account_types,code',
            'amount' => 'required|numeric|min:1',
            'channel' => 'required|in:cash,mpesa,bank,cheque,payroll',
            'external_ref' => 'nullable|string|max:60',
            'txn_date' => 'required|date|before_or_equal:today',
            'description' => 'nullable|string|max:255',
        ]);
        $member = Member::findOrFail($data['member_id']);
        $meta = ['channel' => $data['channel'], 'external_ref' => $data['external_ref'], 'date' => $data['txn_date']];
        if ($data['description']) $meta['description'] = $data['description'];

        try {
            $tx = $data['action'] === 'deposit'
                ? $this->accounts->deposit($member, $data['account'], (float) $data['amount'], $meta)
                : $this->accounts->withdraw($member, $data['account'], (float) $data['amount'], $meta);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $this->sms->send($member->phone, sprintf('%s: KES %s %s %s. Ref %s. New balance KES %s.',
            config('sacco.name'), number_format($tx->amount, 2), $data['action'] === 'deposit' ? 'received to' : 'withdrawn from',
            $tx->account->type->name, $tx->reference, number_format($tx->balance_after, 2)));

        return redirect()->route('transactions.receipt', $tx)->with('success', 'Transaction posted.');
    }

    public function receipt(Transaction $transaction)
    {
        $transaction->load('member', 'account.type', 'poster');
        return view('transactions.receipt', compact('transaction'));
    }

    public function reverse(Request $request, Transaction $transaction)
    {
        $request->validate(['reason' => 'required|string|max:200']);
        try {
            $this->accounts->reverse($transaction, $request->reason);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', 'Transaction reversed.');
    }
}
