<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\CheckoffLine;
use App\Models\CheckoffSchedule;
use App\Models\Employer;
use App\Models\EmployerRemittance;
use App\Models\Loan;
use App\Models\Member;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Employer check-off cycle:
 *  1. generate()   - expected deductions per member for the month (contributions + loan installments)
 *  2. export CSV   - send to the employer's payroll office
 *  3. postFile()   - employer returns what was actually deducted; each line is posted (channel payroll)
 *                    -> Dr Check-off receivable / Cr member account or loan
 *  4. remit()      - employer pays the lump sum: Dr Bank / Cr Check-off receivable
 */
class CheckoffService
{
    public function __construct(private AccountService $accounts, private LoanService $loans, private AccountingService $gl) {}

    public function generate(Employer $employer, string $period): CheckoffSchedule
    {
        $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return DB::transaction(function () use ($employer, $period, $start, $end) {
            $schedule = CheckoffSchedule::firstOrCreate(['employer_id' => $employer->id, 'period' => $period], ['generated_by' => auth()->id()]);
            if ($schedule->status !== 'generated') throw new RuntimeException('This schedule has already been sent/posted; it cannot be regenerated.');
            $schedule->lines()->delete();

            $members = Member::where('employer_id', $employer->id)->where('status', 'active')->get();
            foreach ($members as $m) {
                if ((float) $m->monthly_contribution > 0) {
                    $schedule->lines()->create(['member_id' => $m->id, 'item' => 'DEP', 'expected' => $m->monthly_contribution]);
                }
                foreach ($m->loans()->where('status', 'disbursed')->get() as $loan) {
                    $due = $loan->schedules()->whereIn('status', ['pending', 'partial', 'overdue'])
                        ->whereDate('due_date', '<=', $end)->get()->sum(fn ($s) => $s->balance());
                    if ($due > 0) $schedule->lines()->create(['member_id' => $m->id, 'item' => $loan->loan_no, 'expected' => round($due, 2)]);
                }
            }
            $schedule->update(['expected_total' => $schedule->lines()->sum('expected')]);
            AuditLog::record('checkoff_generated', $schedule, ['lines' => $schedule->lines()->count()]);
            return $schedule->fresh();
        });
    }

    /** Post a single deduction row. Returns null on success or an error message. */
    public function postRow(string $memberNo, string $item, float $amount, string $date, string $ref, ?CheckoffSchedule $schedule = null, string $channel = 'payroll'): ?string
    {
        try {
            DB::transaction(function () use ($memberNo, $item, $amount, $date, $ref, $schedule, $channel) {
                $member = Member::where('member_no', trim($memberNo))->first();
                if (! $member) throw new RuntimeException('member not found');
                $item = strtoupper(trim($item));
                if (str_starts_with($item, 'LN')) {
                    $loan = Loan::where('loan_no', $item)->where('member_id', $member->id)->first();
                    if (! $loan) throw new RuntimeException('loan not found for this member');
                    $this->loans->repay($loan, $amount, $channel, $ref, Carbon::parse($date));
                } else {
                    $this->accounts->deposit($member, $item, $amount, ['channel' => $channel, 'external_ref' => $ref,
                        'date' => $date, 'description' => ($channel === 'opening' ? 'Opening balance ' : 'Check-off ') . $ref]);
                }
                if ($schedule) {
                    $line = CheckoffLine::firstOrNew(['checkoff_schedule_id' => $schedule->id, 'member_id' => $member->id, 'item' => $item]);
                    $line->received = (float) $line->received + $amount;
                    $line->posted = true;
                    $line->save();
                }
            });
            return null;
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    /** @return array{ok:int,total:float,errors:array} */
    public function postFile(string $path, string $date, string $ref, ?CheckoffSchedule $schedule = null, string $channel = 'payroll'): array
    {
        $fh = fopen($path, 'r');
        $header = array_map(fn ($h) => strtolower(trim($h, " \t\n\r\0\x0B\xEF\xBB\xBF")), fgetcsv($fh) ?: []);
        if (array_diff(['member_no', 'item', 'amount'], $header)) {
            fclose($fh);
            throw new RuntimeException('CSV must have columns: member_no, item, amount');
        }
        $ok = 0; $total = 0; $errors = []; $line = 1;
        while (($row = fgetcsv($fh)) !== false) {
            $line++;
            if (count(array_filter($row)) === 0) continue;
            $r = array_combine($header, array_pad($row, count($header), null));
            $amount = (float) str_replace(',', '', (string) $r['amount']);
            if ($amount <= 0) continue;
            if ($err = $this->postRow((string) $r['member_no'], (string) $r['item'], $amount, $date, $ref, $schedule, $channel)) {
                $errors[] = "Line $line ({$r['member_no']} / {$r['item']}): $err";
            } else { $ok++; $total += $amount; }
        }
        fclose($fh);

        if ($schedule) {
            $schedule->update(['received_total' => $schedule->lines()->sum('received'), 'status' => 'posted']);
        }
        return ['ok' => $ok, 'total' => $total, 'errors' => $errors];
    }

    public function remit(Employer $employer, float $amount, string $date, BankAccount $bank, ?string $ref, ?CheckoffSchedule $schedule = null): EmployerRemittance
    {
        return DB::transaction(function () use ($employer, $amount, $date, $bank, $ref, $schedule) {
            $r = EmployerRemittance::create([
                'employer_id' => $employer->id, 'checkoff_schedule_id' => $schedule?->id, 'bank_account_id' => $bank->id,
                'amount' => $amount, 'received_on' => $date, 'reference' => $ref, 'recorded_by' => auth()->id(),
            ]);
            $this->gl->post($date, "Check-off remittance {$employer->name} {$ref}", [
                [$bank->gl_account_id, $amount, 0, $employer->name],
                [$this->gl->gl('checkoff_receivable'), 0, $amount, $employer->name],
            ], $r);
            if ($schedule && $schedule->remittances()->sum('amount') + 0.009 >= (float) $schedule->received_total) {
                $schedule->update(['status' => 'reconciled']);
            }
            return $r;
        });
    }

    /** Outstanding remittances per employer = deductions posted - money received. */
    public function outstanding()
    {
        return Employer::with('schedules')->get()->map(function ($e) {
            $posted = (float) $e->schedules->sum('received_total');
            $remitted = (float) $e->remittances()->sum('amount');
            return ['employer' => $e, 'expected' => (float) $e->schedules->sum('expected_total'),
                    'posted' => $posted, 'remitted' => $remitted, 'outstanding' => round($posted - $remitted, 2)];
        });
    }
}
