<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\CheckoffSchedule;
use App\Models\Employer;
use App\Services\CheckoffService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class EmployerController extends Controller
{
    public function __construct(private CheckoffService $checkoff) {}

    public function index()
    {
        $employers = Employer::withCount(['members' => fn ($q) => $q->where('status', 'active')])->orderBy('name')->get();
        $outstanding = $this->checkoff->outstanding()->keyBy(fn ($r) => $r['employer']->id);
        return view('employers.index', compact('employers', 'outstanding'));
    }

    public function store(Request $r)
    {
        Employer::create($this->validated($r));
        return back()->with('success', 'Employer added.');
    }

    public function update(Request $r, Employer $employer)
    {
        $employer->update($this->validated($r, $employer));
        return back()->with('success', 'Employer updated.');
    }

    public function show(Employer $employer)
    {
        $schedules = $employer->schedules()->withSum('remittances', 'amount')->latest('period')->get();
        $remittances = $employer->remittances()->with('bankAccount')->latest('received_on')->take(20)->get();
        $members = $employer->members()->where('status', 'active')->orderBy('member_no')->get();
        $banks = BankAccount::where('is_active', true)->get();
        return view('employers.show', compact('employer', 'schedules', 'remittances', 'members', 'banks'));
    }

    public function generate(Request $r, Employer $employer)
    {
        $r->validate(['period' => 'required|date_format:Y-m']);
        try { $s = $this->checkoff->generate($employer, $r->period); } catch (RuntimeException $e) { return back()->with('error', $e->getMessage()); }
        return redirect()->route('checkoff.schedules.show', $s)->with('success', 'Check-off schedule generated.');
    }

    public function schedule(CheckoffSchedule $schedule)
    {
        $schedule->load('employer', 'lines.member', 'remittances.bankAccount');
        $banks = BankAccount::where('is_active', true)->get();
        return view('employers.schedule', compact('schedule', 'banks'));
    }

    /** CSV to send to the employer's payroll office. */
    public function export(CheckoffSchedule $schedule)
    {
        $schedule->load('employer', 'lines.member');
        if ($schedule->status === 'generated') $schedule->update(['status' => 'sent']);
        return response()->streamDownload(function () use ($schedule) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['payroll_no', 'name', 'id_no', 'member_no', 'item', 'amount']);
            foreach ($schedule->lines as $l) {
                fputcsv($out, [$l->member->payroll_no, $l->member->full_name, $l->member->national_id, $l->member->member_no, $l->item, $l->expected]);
            }
            fclose($out);
        }, "checkoff-{$schedule->employer->code}-{$schedule->period}.csv");
    }

    /** Employer returns the actual deductions (same CSV columns: member_no,item,amount). */
    public function post(Request $r, CheckoffSchedule $schedule)
    {
        $r->validate(['file' => 'required|file|mimes:csv,txt|max:5120', 'txn_date' => 'required|date']);
        try {
            $res = $this->checkoff->postFile($r->file('file')->getRealPath(), $r->txn_date, "CHK-{$schedule->employer->code}-{$schedule->period}", $schedule);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', "{$res['ok']} line(s) posted, KES " . number_format($res['total'], 2))->with('import_errors', $res['errors']);
    }

    public function remit(Request $r, Employer $employer)
    {
        $d = $r->validate(['amount' => 'required|numeric|min:1', 'received_on' => 'required|date', 'bank_account_id' => 'required|exists:bank_accounts,id',
            'reference' => 'nullable|string|max:60', 'checkoff_schedule_id' => 'nullable|exists:checkoff_schedules,id']);
        try {
            $this->checkoff->remit($employer, (float) $d['amount'], $d['received_on'], BankAccount::find($d['bank_account_id']), $d['reference'] ?? null,
                isset($d['checkoff_schedule_id']) ? CheckoffSchedule::find($d['checkoff_schedule_id']) : null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', 'Remittance recorded.');
    }

    private function validated(Request $r, ?Employer $e = null): array
    {
        $d = $r->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('employers')->ignore($e)], 'name' => 'required|string|max:150',
            'contact_person' => 'nullable|string|max:120', 'phone' => 'nullable|string|max:20', 'email' => 'nullable|email',
            'address' => 'nullable|string|max:255', 'payroll_day' => 'required|integer|min:1|max:31',
        ]);
        $d['is_active'] = $r->boolean('is_active', true);
        return $d;
    }
}
