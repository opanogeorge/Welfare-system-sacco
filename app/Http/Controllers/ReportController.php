<?php

namespace App\Http\Controllers;

use App\Models\AccountType;
use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\LoanRepayment;
use App\Models\Member;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index()
    {
        return view('reports.index');
    }

    /** Loan portfolio by product. */
    public function portfolio()
    {
        $rows = LoanProduct::query()->get()->map(function ($p) {
            $q = Loan::where('loan_product_id', $p->id)->where('status', 'disbursed');
            return [
                'product'   => $p->name,
                'count'     => (clone $q)->count(),
                'disbursed' => (float) (clone $q)->sum('amount_approved'),
                'principal' => (float) (clone $q)->sum('principal_balance'),
                'interest'  => (float) (clone $q)->sum('interest_balance'),
                'penalty'   => (float) (clone $q)->sum('penalty_balance'),
            ];
        });
        return $this->respond('reports.portfolio', compact('rows'), $rows, 'loan-portfolio');
    }

    /** Arrears & SASRA classification / portfolio at risk. */
    public function arrears(Request $request)
    {
        $loans = Loan::with('member', 'product')->where('status', 'disbursed')->get()
            ->map(fn ($l) => [
                'loan' => $l, 'days' => $l->daysInArrears(), 'arrears' => $l->arrears(), 'class' => $l->classification(),
            ])
            ->filter(fn ($r) => $request->boolean('all') || $r['days'] > 0)
            ->sortByDesc('days')->values();

        $book = (float) Loan::where('status', 'disbursed')->sum('principal_balance');
        $buckets = collect(['Performing', 'Watch', 'Substandard', 'Doubtful', 'Loss'])->mapWithKeys(fn ($c) => [$c => [
            'count' => $loans->where('class', $c)->count(),
            'principal' => $loans->where('class', $c)->sum(fn ($r) => (float) $r['loan']->principal_balance),
        ]]);
        $provisionRates = ['Performing' => 1, 'Watch' => 5, 'Substandard' => 25, 'Doubtful' => 50, 'Loss' => 100];

        if ($request->export === 'csv') {
            return $this->csv($loans->map(fn ($r) => [
                'Loan No' => $r['loan']->loan_no, 'Member' => $r['loan']->member->member_no . ' ' . $r['loan']->member->full_name,
                'Phone' => $r['loan']->member->phone, 'Product' => $r['loan']->product->name,
                'Outstanding' => $r['loan']->outstanding, 'Arrears' => $r['arrears'], 'Days' => $r['days'], 'Class' => $r['class'],
            ]), 'arrears');
        }
        return view('reports.arrears', compact('loans', 'book', 'buckets', 'provisionRates'));
    }

    /** Member balances per account type. */
    public function balances(Request $request)
    {
        $types = AccountType::all();
        $members = Member::with('accounts')->whereIn('status', ['active', 'dormant'])->orderBy('member_no')->paginate(50);
        $totals = $types->mapWithKeys(fn ($t) => [$t->id => (float) $t->accounts()->sum('balance')]);
        if ($request->export === 'csv') return redirect()->route('members.index', ['export' => 'csv']);
        return view('reports.balances', compact('types', 'members', 'totals'));
    }

    /** Cash book: money in / money out per channel for a date range. */
    public function cashbook(Request $request)
    {
        $from = $request->input('from', today()->startOfMonth()->toDateString());
        $to = $request->input('to', today()->toDateString());

        $rows = Transaction::whereBetween('txn_date', [$from, $to])->where('channel', '!=', 'internal')
            ->select('type', 'channel', 'direction', DB::raw('COUNT(*) n'), DB::raw('SUM(amount) total'))
            ->groupBy('type', 'channel', 'direction')->orderBy('type')->get();
        $expenses = (float) Expense::whereBetween('spent_on', [$from, $to])->sum('amount');

        if ($request->export === 'csv') {
            return $this->csv($rows->map(fn ($r) => ['Type' => $r->type, 'Channel' => $r->channel, 'Direction' => $r->direction, 'Count' => $r->n, 'Total' => $r->total]), 'cashbook');
        }
        return view('reports.cashbook', compact('rows', 'from', 'to', 'expenses'));
    }

    /** Income statement-style summary: interest, fees and penalties earned vs expenses. */
    public function income(Request $request)
    {
        $from = $request->input('from', today()->startOfYear()->toDateString());
        $to = $request->input('to', today()->toDateString());

        $income = [
            'Loan interest'        => (float) LoanRepayment::whereBetween('paid_on', [$from, $to])->sum('interest_paid'),
            'Penalties'            => (float) LoanRepayment::whereBetween('paid_on', [$from, $to])->sum('penalty_paid'),
            'Loan processing fees' => (float) Loan::whereBetween('disbursed_on', [$from, $to])->sum('processing_fee'),
            'Insurance collected'  => (float) Loan::whereBetween('disbursed_on', [$from, $to])->sum('insurance_fee'),
            'Registration & other fees' => (float) Transaction::where('type', 'fee')->whereNull('loan_id')->whereBetween('txn_date', [$from, $to])->sum('amount'),
        ];
        $expenses = Expense::whereBetween('spent_on', [$from, $to])->select('category', DB::raw('SUM(amount) total'))->groupBy('category')->pluck('total', 'category');
        return view('reports.income', compact('income', 'expenses', 'from', 'to'));
    }

    /** Guarantor liability: current exposure per guarantor (falls as borrowers repay). */
    public function guarantors(Request $request)
    {
        $bucket = function ($x) {
            if ($x->loan->status !== 'disbursed') return 'not_disbursed';
            $d = $x->loan->daysInArrears();
            return $d === 0 ? 'current' : ($d <= 30 ? 'd1_30' : ($d <= 90 ? 'd31_90' : 'd90'));
        };
        $recovered = \App\Models\LoanGuarantor::where('recovered_amount', '>', 0)->selectRaw('member_id, SUM(recovered_amount) total')->groupBy('member_id')->pluck('total', 'member_id');
        $rows = \App\Models\LoanGuarantor::with('member', 'loan.member')->where('status', 'accepted')
            ->whereHas('loan', fn ($q) => $q->whereIn('status', ['approved', 'disbursed']))
            ->when($request->q, fn ($q, $s) => $q->whereHas('member', fn ($m) => $m->where('member_no', 'like', "%$s%")->orWhere('last_name', 'like', "%$s%")->orWhere('first_name', 'like', "%$s%")))
            ->get()
            ->groupBy('member_id')->map(function ($g) use ($bucket, $recovered) {
                $b = ['current' => 0, 'd1_30' => 0, 'd31_90' => 0, 'd90' => 0, 'not_disbursed' => 0];
                foreach ($g as $x) $b[$bucket($x)] += $x->exposure();
                $m = $g->first()->member;
                return ['member' => $m, 'loans' => $g->count(), 'guaranteed' => $g->sum('amount'), 'exposure' => array_sum($b),
                    'at_risk' => $b['d31_90'] + $b['d90'], 'buckets' => $b, 'limit' => $m->guaranteeLimit(),
                    'capacity' => $m->freeGuaranteeCapacity(), 'recovered' => (float) ($recovered[$m->id] ?? 0), 'items' => $g];
            })
            ->when($request->boolean('at_risk'), fn ($c) => $c->filter(fn ($r) => $r['at_risk'] > 0))
            ->sortByDesc($request->input('sort', 'exposure'))->values();
        $totals = collect(['current', 'd1_30', 'd31_90', 'd90', 'not_disbursed'])->mapWithKeys(fn ($k) => [$k => $rows->sum(fn ($r) => $r['buckets'][$k])]);
        if ($request->export === 'csv') {
            return $this->csv($rows->map(fn ($r) => ['Member No' => $r['member']->member_no, 'Name' => $r['member']->full_name, 'Loans' => $r['loans'],
                'Guaranteed' => $r['guaranteed'], 'Current exposure' => $r['exposure'], 'Exposure - borrower current' => $r['buckets']['current'],
                'Exposure 1-30 days' => $r['buckets']['d1_30'], 'Exposure 31-90 days' => $r['buckets']['d31_90'], 'Exposure 90+ days' => $r['buckets']['d90'],
                'Not yet disbursed' => $r['buckets']['not_disbursed'], 'Limit' => $r['limit'], 'Free capacity' => $r['capacity'], 'Recovered to date' => $r['recovered'],
                'Loans guaranteed' => $r['items']->map(fn ($x) => $x->loan->loan_no)->implode(' ')]), 'guarantor-liability');
        }
        return view('reports.guarantors', compact('rows', 'totals'));
    }

    /** Member register with KYC status. */
    public function register(Request $request)
    {
        $rows = Member::with('category', 'employerRecord')->withCount(['documents as kyc_docs' => fn ($q) => $q->whereIn('type', ['national_id', 'passport'])])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))->orderBy('member_no')->get();
        if ($request->export === 'csv') {
            return $this->csv($rows->map(fn ($m) => ['Member No' => $m->member_no, 'Name' => $m->full_name, 'ID' => $m->national_id, 'KRA PIN' => $m->kra_pin,
                'Gender' => $m->gender, 'DOB' => $m->date_of_birth?->toDateString(), 'Phone' => $m->phone, 'Email' => $m->email,
                'Employer' => $m->employerRecord?->name ?? $m->employer, 'Category' => $m->category?->name, 'Joined' => $m->joined_on->toDateString(),
                'Status' => $m->status, 'Risk' => $m->risk_rating, 'PEP' => $m->is_pep ? 'Yes' : 'No', 'ID on file' => $m->kyc_docs ? 'Yes' : 'No',
                'Data consent' => $m->data_consent_at ? 'Yes' : 'No']), 'member-register');
        }
        $stats = ['total' => $rows->count(), 'male' => $rows->where('gender', 'male')->count(), 'female' => $rows->where('gender', 'female')->count(),
                  'no_kyc' => $rows->where('kyc_docs', 0)->count(), 'high_risk' => $rows->where('risk_rating', 'high')->count()];
        return view('reports.register', compact('rows', 'stats'));
    }

    /** Income earned per member (interest + penalties + fees) vs dividends/interest paid to them. */
    public function profitability(Request $request)
    {
        $from = $request->input('from', today()->startOfYear()->toDateString());
        $to = $request->input('to', today()->toDateString());
        $earned = LoanRepayment::join('loans', 'loans.id', '=', 'loan_repayments.loan_id')->whereBetween('paid_on', [$from, $to])
            ->groupBy('loans.member_id')->selectRaw('loans.member_id, SUM(interest_paid) interest, SUM(penalty_paid) penalty')->get()->keyBy('member_id');
        $fees = Transaction::where('type', 'fee')->whereBetween('txn_date', [$from, $to])->groupBy('member_id')->selectRaw('member_id, SUM(amount) t')->pluck('t', 'member_id');
        $paid = Transaction::whereIn('type', ['dividend', 'interest'])->whereBetween('txn_date', [$from, $to])->groupBy('member_id')->selectRaw('member_id, SUM(amount) t')->pluck('t', 'member_id');

        $ids = $earned->keys()->merge($fees->keys())->merge($paid->keys())->unique();
        $rows = Member::whereIn('id', $ids)->get()->map(function ($m) use ($earned, $fees, $paid) {
            $inc = (float) ($earned[$m->id]->interest ?? 0) + (float) ($earned[$m->id]->penalty ?? 0) + (float) ($fees[$m->id] ?? 0);
            return ['member' => $m, 'income' => $inc, 'paid' => (float) ($paid[$m->id] ?? 0), 'net' => $inc - (float) ($paid[$m->id] ?? 0)];
        })->sortByDesc('net')->values();
        return view('reports.profitability', compact('rows', 'from', 'to'));
    }

    /** AML: large cash transactions flagged at posting time, plus high-risk / PEP members. */
    public function aml()
    {
        $flags = AuditLog::with('user')->where('action', 'aml_flag')->latest()->paginate(30);
        $members = Member::whereIn('id', $flags->getCollection()->pluck('data.member_id')->filter())->get()->keyBy('id');
        $highRisk = Member::where(fn ($q) => $q->where('risk_rating', 'high')->orWhere('is_pep', true))->get();
        return view('reports.aml', compact('flags', 'members', 'highRisk'));
    }

    public function logins(Request $request)
    {
        $logs = AuditLog::with('user')->whereIn('action', ['login', 'login_failed', 'otp_failed', 'logout', 'api_login_failed'])
            ->when($request->failed, fn ($q) => $q->whereIn('action', ['login_failed', 'otp_failed', 'api_login_failed']))
            ->latest()->paginate(50)->withQueryString();
        return view('reports.logins', compact('logs'));
    }

    public function audit(Request $request)
    {
        $logs = AuditLog::with('user')->when($request->action, fn ($q, $a) => $q->where('action', $a))->latest()->paginate(50)->withQueryString();
        return view('reports.audit', compact('logs'));
    }

    private function respond(string $view, array $data, $rows, string $name)
    {
        if (request('export') === 'csv') return $this->csv(collect($rows), $name);
        return view($view, $data);
    }

    private function csv($rows, string $name)
    {
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            $first = true;
            foreach ($rows as $r) {
                if ($first) { fputcsv($out, array_keys($r)); $first = false; }
                fputcsv($out, array_values($r));
            }
            fclose($out);
        }, "$name-" . now()->format('Ymd') . '.csv');
    }
}
