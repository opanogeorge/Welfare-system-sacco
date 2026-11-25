<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanRecoveryAction;
use App\Models\LoanSchedule;
use App\Models\Member;
use App\Models\ReminderLog;
use App\Models\ReminderRule;
use App\Models\User;
use App\Models\WelfareContribution;
use Illuminate\Support\Collection;

/**
 * Rule-based reminders. Each active rule finds who it applies to today, fills in the message
 * and sends it once (ref_key stops repeats). Runs daily from `sacco:send-reminders`.
 */
class ReminderService
{
    /** event => [label, meaning of "days"] */
    public const EVENTS = [
        'loan_due'             => ['Installment coming up', 'days before the due date (0 = on the due date)'],
        'loan_overdue'         => ['Installment overdue', 'days after the due date'],
        'loan_maturity'        => ['Loan about to mature', 'days before the final installment'],
        'promise_due'          => ['Promise to pay due', 'days before the promised date (0 = on the day)'],
        'contribution_due'     => ['Monthly contribution reminder', 'day of the month to send'],
        'contribution_arrears' => ['Contribution arrears', 'day of the month to send'],
        'welfare_due'          => ['Unpaid welfare levy', 'repeat every N days while unpaid'],
    ];

    public const PLACEHOLDERS = ['{first_name}', '{name}', '{member_no}', '{loan_no}', '{amount}', '{due_date}', '{days}', '{arrears}', '{outstanding}', '{paybill}', '{sacco}', '{event}', '{borrower}', '{installment_no}'];

    public function __construct(private SmsService $sms, private NotificationService $notify) {}

    /** @return array{sent:int, failed:int, items:array} */
    public function run(bool $dryRun = false, ?ReminderRule $only = null): array
    {
        $out = ['sent' => 0, 'failed' => 0, 'items' => []];
        $rules = $only ? collect([$only]) : ReminderRule::where('is_active', true)->get();
        foreach ($rules as $rule) {
            foreach ($this->targets($rule) as $t) {
                if (ReminderLog::where('ref_key', $t['key'])->exists()) continue;
                $text = $this->fill($rule->message, $t['vars']);
                $out['items'][] = ['rule' => $rule->name, 'member' => $t['member'], 'text' => $text, 'loan' => $t['loan'] ?? null];
                if ($dryRun) continue;
                $ok = $this->deliver($rule, $t['member'], $text);
                ReminderLog::create(['reminder_rule_id' => $rule->id, 'member_id' => $t['member']->id, 'loan_id' => $t['loan']?->id ?? null,
                    'ref_key' => $t['key'], 'channel' => $rule->channel, 'message' => $text, 'status' => $ok ? 'sent' : 'failed']);
                $ok ? $out['sent']++ : $out['failed']++;

                if ($rule->notify_guarantors && ($loan = $t['loan'] ?? null) && $rule->guarantor_message) {
                    foreach ($loan->guarantors()->where('status', 'accepted')->with('member')->get() as $g) {
                        $gt = $this->fill($rule->guarantor_message, $t['vars'] + ['{first_name}' => $g->member->first_name, '{name}' => $g->member->full_name, '{borrower}' => $loan->member->full_name]);
                        $gok = $this->sms->send($g->member->phone, $gt, $g->member->id);
                        ReminderLog::create(['reminder_rule_id' => $rule->id, 'member_id' => $g->member->id, 'loan_id' => $loan->id, 'ref_key' => $t['key'] . "-g{$g->id}",
                            'recipient' => 'guarantor', 'channel' => 'sms', 'message' => $gt, 'status' => $gok ? 'sent' : 'failed']);
                    }
                }
            }
        }
        return $out;
    }

    /** Staff "send reminder now" on a loan. */
    public function sendLoanReminderNow(Loan $loan, User $by, ?string $custom = null): bool
    {
        $vars = $this->loanVars($loan, $loan->schedules()->whereIn('status', ['pending', 'partial'])->orderBy('due_date')->first());
        $text = $this->fill($custom ?: ($loan->daysInArrears() > 0
            ? 'Dear {first_name}, loan {loan_no} is {days} days overdue with arrears of KES {arrears}. Please pay today via Paybill {paybill}, Acc {loan_no}. - {sacco}'
            : 'Dear {first_name}, your loan {loan_no} installment of KES {amount} is due on {due_date}. Paybill {paybill}, Acc {loan_no}. - {sacco}'), $vars);
        $ok = $this->sms->send($loan->member->phone, $text, $loan->member_id);
        ReminderLog::create(['member_id' => $loan->member_id, 'loan_id' => $loan->id, 'channel' => 'sms', 'message' => $text, 'status' => $ok ? 'sent' : 'failed', 'sent_by' => $by->id]);
        return $ok;
    }

    /** @return Collection<int, array{key:string, member:Member, loan?:Loan|null, vars:array}> */
    private function targets(ReminderRule $r): Collection
    {
        $today = today();
        $days = (int) $r->days;
        return match ($r->event) {
            'loan_due', 'loan_overdue' => LoanSchedule::with('loan.member', 'loan.product')
                ->whereDate('due_date', $r->event === 'loan_due' ? $today->copy()->addDays($days) : $today->copy()->subDays($days))
                ->whereIn('status', ['pending', 'partial'])->whereHas('loan', fn ($q) => $q->where('status', 'disbursed'))->get()
                ->filter(fn ($s) => $s->balance() > 0.5)
                ->map(fn ($s) => ['key' => "r{$r->id}-s{$s->id}", 'member' => $s->loan->member, 'loan' => $s->loan, 'vars' => $this->loanVars($s->loan, $s)])->values(),

            'loan_maturity' => Loan::with('member')->where('status', 'disbursed')->whereDate('maturity_date', $today->copy()->addDays($days))->get()
                ->map(fn ($l) => ['key' => "r{$r->id}-l{$l->id}", 'member' => $l->member, 'loan' => $l, 'vars' => $this->loanVars($l, null)])->values(),

            'promise_due' => LoanRecoveryAction::with('loan.member')->where('promise_status', 'open')->whereDate('promise_date', $today->copy()->addDays($days))->get()
                ->filter(fn ($a) => $a->loan && $a->loan->member)
                ->map(fn ($a) => ['key' => "r{$r->id}-p{$a->id}", 'member' => $a->loan->member, 'loan' => $a->loan,
                    'vars' => $this->loanVars($a->loan, null) + ['{amount}' => number_format((float) $a->promise_amount, 2), '{due_date}' => $a->promise_date->format('d/m/Y')]])->values(),

            'contribution_due' => $today->day === max(1, min($days, $today->daysInMonth))
                ? Member::active()->where('monthly_contribution', '>', 0)->get()
                    ->map(fn ($m) => ['key' => "r{$r->id}-m{$m->id}-" . $today->format('Ym'), 'member' => $m, 'loan' => null,
                        'vars' => $this->memberVars($m) + ['{amount}' => number_format((float) $m->monthly_contribution, 2)]])->values()
                : collect(),

            'contribution_arrears' => $today->day === max(1, min($days, $today->daysInMonth))
                ? app(SavingsService::class)->contributionArrears(3)->where('arrears', '>', 0)->values()
                    ->map(fn ($row) => ['key' => "r{$r->id}-m{$row['member']->id}-" . $today->format('Ym'), 'member' => $row['member'], 'loan' => null,
                        'vars' => $this->memberVars($row['member']) + ['{arrears}' => number_format($row['arrears'], 2), '{amount}' => number_format($row['arrears'], 2)]])
                : collect(),

            'welfare_due' => WelfareContribution::with('member', 'event')->where('status', 'due')->get()
                ->filter(fn ($c) => $c->member && ($age = (int) $c->created_at->copy()->startOfDay()->diffInDays($today)) >= max(1, $days) && $age % max(1, $days) === 0)
                ->map(fn ($c) => ['key' => "r{$r->id}-w{$c->id}-" . $today->format('Ymd'), 'member' => $c->member, 'loan' => null,
                    'vars' => $this->memberVars($c->member) + ['{amount}' => number_format((float) $c->amount, 2),
                        '{event}' => $c->event?->title ?? 'monthly welfare contribution', '{loan_no}' => $c->event ? "{$c->member->member_no}-W{$c->event->id}" : "{$c->member->member_no}-WLF"]])->values(),

            default => collect(),
        };
    }

    private function memberVars(Member $m): array
    {
        return ['{first_name}' => $m->first_name, '{name}' => $m->full_name, '{member_no}' => $m->member_no,
            '{paybill}' => (string) config('sacco.mpesa.paybill'), '{sacco}' => sacco_name()];
    }

    private function loanVars(Loan $l, ?LoanSchedule $s): array
    {
        return $this->memberVars($l->member) + [
            '{loan_no}' => $l->loan_no, '{outstanding}' => number_format($l->outstanding, 2), '{arrears}' => number_format($l->arrears(), 2),
            '{amount}' => number_format($s ? $s->balance() : $l->outstanding, 2), '{due_date}' => ($s?->due_date ?? $l->maturity_date)?->format('d/m/Y') ?? '',
            '{days}' => (string) $l->daysInArrears(), '{installment_no}' => (string) ($s?->installment_no ?? ''), '{borrower}' => $l->member->full_name,
        ];
    }

    public function fill(string $tpl, array $vars): string
    {
        return trim(strtr($tpl, $vars));
    }

    private function deliver(ReminderRule $r, Member $m, string $text): bool
    {
        $ok = true;
        if (in_array($r->channel, ['sms', 'both'])) $ok = $this->sms->send($m->phone, $text, $m->id);
        if (in_array($r->channel, ['email', 'both']) && $m->email) {
            try { $this->notify->email($m->email, 'Reminder from ' . sacco_name(), $text, $m->id); } catch (\Throwable $e) { $ok = $ok && $r->channel === 'both'; }
        }
        return $ok;
    }

    public static function defaults(): array
    {
        $g = 'Dear {first_name}, {borrower} whom you guaranteed (loan {loan_no}) is {days} days in arrears, KES {arrears}. Please encourage them to pay. Your guarantee may be called. - {sacco}';
        return [
            ['Installment due in 3 days', 'loan_due', 3, 'Dear {first_name}, your loan {loan_no} installment of KES {amount} is due on {due_date}. Pay via Paybill {paybill}, Acc {loan_no}. - {sacco}', false, null],
            ['Installment due today', 'loan_due', 0, 'Dear {first_name}, your loan {loan_no} installment of KES {amount} is due TODAY. Paybill {paybill}, Acc {loan_no}. Thank you. - {sacco}', false, null],
            ['1 day overdue', 'loan_overdue', 1, 'Dear {first_name}, your loan {loan_no} installment of KES {amount} due {due_date} is unpaid. Please pay today to avoid penalties. Paybill {paybill}, Acc {loan_no}. - {sacco}', false, null],
            ['7 days overdue', 'loan_overdue', 7, 'Dear {first_name}, loan {loan_no} is now {days} days in arrears (KES {arrears}). Penalties apply. Pay via Paybill {paybill}, Acc {loan_no} or call us. - {sacco}', false, null],
            ['30 days overdue + guarantors', 'loan_overdue', 30, 'Dear {first_name}, loan {loan_no} is {days} days in arrears (KES {arrears}). Your guarantors have been informed. Pay now or visit our office to avoid recovery action. - {sacco}', true, $g],
            ['60 days overdue - final notice', 'loan_overdue', 60, 'FINAL NOTICE: {first_name}, loan {loan_no} is {days} days in arrears (KES {arrears}). We will recover from your deposits and guarantors unless you pay within 7 days. - {sacco}', true, $g],
            ['Loan maturing in 30 days', 'loan_maturity', 30, 'Dear {first_name}, your loan {loan_no} matures on {due_date}. Outstanding KES {outstanding}. - {sacco}', false, null],
            ['Promise to pay due tomorrow', 'promise_due', 1, 'Dear {first_name}, a reminder of your promise to pay KES {amount} on loan {loan_no} by {due_date}. Paybill {paybill}, Acc {loan_no}. - {sacco}', false, null],
            ['Monthly contribution reminder', 'contribution_due', 25, 'Dear {first_name}, please remember your monthly contribution of KES {amount}. Paybill {paybill}, Acc {member_no}. - {sacco}', false, null],
            ['Contribution arrears', 'contribution_arrears', 5, 'Dear {first_name}, your contributions are KES {arrears} behind. Please catch up via Paybill {paybill}, Acc {member_no}. - {sacco}', false, null],
            ['Welfare levy unpaid', 'welfare_due', 7, 'Dear {first_name}, your welfare contribution of KES {amount} for {event} is pending. Paybill {paybill}, Acc {loan_no}. Thank you. - {sacco}', false, null],
        ];
    }

    public static function seedDefaults(): void
    {
        if (ReminderRule::exists()) return;
        foreach (self::defaults() as [$name, $event, $days, $msg, $g, $gm]) {
            ReminderRule::create(['name' => $name, 'event' => $event, 'days' => $days, 'message' => $msg, 'notify_guarantors' => $g, 'guarantor_message' => $gm, 'channel' => 'sms', 'is_active' => true]);
        }
    }
}
