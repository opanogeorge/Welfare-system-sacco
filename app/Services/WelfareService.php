<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Member;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WelfareBenefit;
use App\Models\WelfareContribution;
use App\Models\WelfareEvent;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Welfare / benevolent fund.
 *  - Events (bereavement, wedding, new baby, sickness ...) for a member.
 *  - Members give voluntarily, and/or a fixed levy is charged to every active member:
 *    deducted from their savings (or deposits), otherwise left "due" for them to pay by M-Pesa/cash.
 *  - Optional fixed monthly welfare contribution that builds the fund reserve.
 *  - The fund pays out the money raised plus the benefit from the reserve (benefit schedule).
 * GL: contributions credit 2400 Welfare Fund; payouts debit it.
 */
class WelfareService
{
    public function __construct(private AccountService $accounts, private NotificationService $notify) {}

    public function fundBalance(): float
    {
        $gl = \App\Models\GlAccount::where('system_key', 'welfare_fund')->first();
        if (! $gl) return 0;
        $row = DB::table('journal_lines')->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_lines.gl_account_id', $gl->id)->where('journal_entries.status', '!=', 'draft')
            ->selectRaw('COALESCE(SUM(credit),0) - COALESCE(SUM(debit),0) as bal')->first();
        return round((float) ($row->bal ?? 0), 2);
    }

    public function create(array $d, ?User $by, string $status = 'draft'): WelfareEvent
    {
        $d['fund_contribution'] = $d['fund_contribution'] ?? WelfareBenefit::amountFor($d['type'], $d['relationship'] ?? null);
        return WelfareEvent::create($d + [
            'event_no' => 'WF' . now()->format('y') . str_pad((string) (WelfareEvent::max('id') + 1), 4, '0', STR_PAD_LEFT),
            'status' => $status, 'created_by' => $by?->id,
        ]);
    }

    /** Approve and open the event: announces it and charges the levy. */
    public function open(WelfareEvent $e, User $by, bool $announce = true): array
    {
        if (! in_array($e->status, ['draft', 'requested'])) throw new RuntimeException('Only draft or requested events can be opened.');
        $e->update(['status' => 'open', 'approved_by' => $by->id, 'approved_at' => now()]);
        AuditLog::record('welfare_opened', $e, ['levy' => $e->levy_amount, 'mode' => $e->mode]);

        $result = ['charged' => 0, 'deducted' => 0, 'due' => 0];
        if ($e->mode !== 'voluntary' && $e->levy_amount > 0) $result = $this->chargeLevy($e, $by);

        if ($announce) {
            $this->announce($e);
        }
        return $result;
    }

    public function announce(WelfareEvent $e): int
    {
        $msg = $this->announcement($e);
        $members = Member::active()->where('id', '!=', $e->member_id)->get();
        return $this->notify->bulk($members, $msg, ['sms'])['count'];
    }

    public function announcement(WelfareEvent $e): string
    {
        $who = $e->member->full_name;
        $lead = match ($e->type) {
            'bereavement' => "We regret to announce the loss of " . ($e->relationship && $e->relationship !== 'self' ? "the " . strtolower(WelfareEvent::RELATIONSHIPS[$e->relationship] ?? $e->relationship) . " of our member $who" : "our member $who") . '.',
            'wedding' => "Our member $who is getting married. Let's celebrate together!",
            'birth' => "Our member $who has been blessed with a baby!",
            'sickness' => "Our member $who is unwell and needs our support.",
            default => "{$e->title}: our member $who needs our support.",
        };
        $pay = 'Paybill ' . config('sacco.mpesa.paybill') . ", Acc {member_no}-W{$e->id}";
        $ask = match (true) {
            $e->mode !== 'voluntary' && $e->levy_amount > 0 && $e->levy_source !== 'none' =>
                ' A levy of KES ' . number_format($e->levy_amount) . ' will be deducted from your ' . ($e->levy_source === 'DEP' ? 'deposits' : 'savings') . '. If your balance is too low, please pay via ' . $pay . '.',
            $e->mode !== 'voluntary' && $e->levy_amount > 0 => ' Each member is asked to contribute KES ' . number_format($e->levy_amount) . " via $pay.",
            default => ' Contribute' . ($e->suggested_amount > 0 ? ' (suggested KES ' . number_format($e->suggested_amount) . ')' : '') . " via $pay or the member portal.",
        };
        return "Dear {first_name}, $lead$ask - " . sacco_name();
    }

    /** Charge the fixed levy to every active member (except the beneficiary). Safe to re-run: never charges twice. */
    public function chargeLevy(WelfareEvent $e, ?User $by = null): array
    {
        $out = ['charged' => 0, 'deducted' => 0, 'due' => 0];
        Member::active()->where('id', '!=', $e->member_id)->chunkById(200, function ($members) use ($e, $by, &$out) {
            foreach ($members as $m) {
                $c = WelfareContribution::firstOrCreate(['uniq_key' => "L{$e->id}-{$m->id}"], [
                    'welfare_event_id' => $e->id, 'member_id' => $m->id, 'amount' => $e->levy_amount, 'kind' => 'levy', 'status' => 'due',
                ]);
                if ($c->wasRecentlyCreated) $out['charged']++;
                if ($c->status === 'due') $this->tryDeduct($c, $e->levy_source, $by) ? $out['deducted']++ : $out['due']++;
            }
        });
        $this->refresh($e);
        return $out;
    }

    /** Retry deducting levies still due (e.g. after salaries/check-off land). */
    public function retryDeductions(WelfareEvent $e, ?User $by = null): array
    {
        $out = ['deducted' => 0, 'due' => 0];
        foreach ($e->contributions()->where('status', 'due')->with('member')->get() as $c) {
            $this->tryDeduct($c, $e->levy_source, $by) ? $out['deducted']++ : $out['due']++;
        }
        $this->refresh($e);
        return $out;
    }

    private function tryDeduct(WelfareContribution $c, string $source, ?User $by): bool
    {
        if (! in_array($source, ['SAV', 'DEP'])) return false;
        $acc = $c->member->account($source);
        if (! $acc) return false;
        $available = $source === 'SAV' ? $acc->availableBalance() : (float) $acc->balance - (float) $acc->frozen_amount;
        if ($available < (float) $c->amount) return false;

        DB::transaction(function () use ($c, $acc, $by) {
            $label = $c->event ? "Welfare levy {$c->event->event_no}: {$c->event->title}" : "Monthly welfare contribution {$c->period}";
            $tx = $this->accounts->post($acc, 'welfare', 'debit', (float) $c->amount, [
                'reference' => NumberGenerator::reference('WF'), 'channel' => 'internal', 'description' => $label, 'posted_by' => $by?->id,
            ]);
            $c->update(['status' => 'paid', 'channel' => $acc->type->code === 'DEP' ? 'deposits' : 'savings', 'reference' => $tx->reference, 'paid_at' => now(), 'posted_by' => $by?->id]);
        });
        return true;
    }

    /**
     * Money received for welfare (M-Pesa, cash, bank). Settles the member's oldest dues for that event
     * (or any dues when no event given); the rest is a voluntary contribution.
     */
    public function receive(Member $member, float $amount, ?int $eventId, string $channel, ?string $ref, ?int $postedBy = null): Transaction
    {
        if ($amount <= 0) throw new RuntimeException('Amount must be more than zero.');
        $event = $eventId ? WelfareEvent::find($eventId) : null;
        if ($eventId && (! $event || in_array($event->status, ['rejected', 'paid']))) $event = null; // money still goes to the fund

        return DB::transaction(function () use ($member, $amount, $event, $channel, $ref, $postedBy) {
            $tx = Transaction::create([
                'reference' => NumberGenerator::reference('WF'), 'member_id' => $member->id, 'type' => 'welfare', 'direction' => 'credit',
                'amount' => round($amount, 2), 'channel' => $channel, 'external_ref' => $ref, 'txn_date' => today(), 'posted_by' => $postedBy ?? auth()->id(),
                'description' => $event ? "Welfare contribution {$event->event_no}: {$event->title}" : 'Welfare contribution',
            ]);

            $left = $amount;
            $dues = WelfareContribution::where('member_id', $member->id)->where('status', 'due')
                ->when($event, fn ($q) => $q->where('welfare_event_id', $event->id))->orderBy('id')->get();
            foreach ($dues as $d) {
                if ($left + 0.001 < (float) $d->amount) break;
                $d->update(['status' => 'paid', 'channel' => $channel, 'reference' => $ref ?? $tx->reference, 'paid_at' => now(), 'posted_by' => $postedBy]);
                $left -= (float) $d->amount;
                if ($d->event) $this->refresh($d->event);
            }
            if ($left > 0.009) {
                WelfareContribution::create(['welfare_event_id' => $event?->id, 'member_id' => $member->id, 'amount' => round($left, 2),
                    'kind' => $event ? 'voluntary' : 'monthly', 'status' => 'paid', 'channel' => $channel, 'reference' => $ref ?? $tx->reference,
                    'paid_at' => now(), 'posted_by' => $postedBy, 'period' => $event ? null : now()->format('Y-m')]);
            }
            if ($event) $this->refresh($event);
            return $tx;
        });
    }

    /** A member gives from their own savings (portal button). */
    public function giveFromSavings(Member $member, WelfareEvent $e, float $amount): void
    {
        if ($e->status !== 'open') throw new RuntimeException('This event is no longer open for contributions.');
        $acc = $member->account('SAV');
        if (! $acc || $acc->availableBalance() < $amount) throw new RuntimeException('Your available savings balance is not enough.');
        DB::transaction(function () use ($member, $e, $amount, $acc) {
            $due = $e->contributions()->where('member_id', $member->id)->where('status', 'due')->first();
            $tx = $this->accounts->post($acc, 'welfare', 'debit', $amount, ['reference' => NumberGenerator::reference('WF'), 'channel' => 'internal',
                'description' => "Welfare contribution {$e->event_no}: {$e->title}"]);
            $left = $amount;
            if ($due && $amount + 0.001 >= (float) $due->amount) {
                $due->update(['status' => 'paid', 'channel' => 'savings', 'reference' => $tx->reference, 'paid_at' => now()]);
                $left -= (float) $due->amount;
            }
            if ($left > 0.009) {
                WelfareContribution::create(['welfare_event_id' => $e->id, 'member_id' => $member->id, 'amount' => round($left, 2), 'kind' => 'voluntary',
                    'status' => 'paid', 'channel' => 'savings', 'reference' => $tx->reference, 'paid_at' => now()]);
            }
        });
        $this->refresh($e);
    }

    public function waive(WelfareContribution $c, User $by): void
    {
        if ($c->status !== 'due') throw new RuntimeException('Only unpaid dues can be waived.');
        $c->update(['status' => 'waived', 'posted_by' => $by->id]);
        AuditLog::record('welfare_waived', $c, ['amount' => $c->amount]);
        if ($c->event) $this->refresh($c->event);
    }

    /** Pay the beneficiary: credit their savings, or cash / bank / M-Pesa. */
    public function payout(WelfareEvent $e, float $amount, string $channel, ?string $ref, User $by): void
    {
        if (! in_array($e->status, ['open', 'closed'])) throw new RuntimeException('Open or closed events only.');
        if ($amount <= 0) throw new RuntimeException('Enter the amount to pay.');
        if ($amount > $this->fundBalance() + 0.01) throw new RuntimeException('The welfare fund balance (KES ' . number_format($this->fundBalance(), 2) . ') is not enough for this payout.');

        DB::transaction(function () use ($e, $amount, $channel, $ref, $by) {
            $desc = "Welfare payout {$e->event_no}: {$e->title}";
            if ($channel === 'savings') {
                $this->accounts->post($this->accounts->accountFor($e->member, 'SAV'), 'welfare_payout', 'credit', $amount,
                    ['reference' => NumberGenerator::reference('WP'), 'channel' => 'internal', 'description' => $desc, 'external_ref' => $ref]);
            } else {
                Transaction::create(['reference' => NumberGenerator::reference('WP'), 'member_id' => $e->member_id, 'type' => 'welfare_payout',
                    'direction' => 'debit', 'amount' => round($amount, 2), 'channel' => $channel, 'external_ref' => $ref, 'txn_date' => today(),
                    'posted_by' => $by->id, 'description' => $desc]);
            }
            $paid = (float) $e->paid_out + $amount;
            $e->update(['paid_out' => $paid, 'paid_on' => today(), 'payout_channel' => $channel, 'payout_ref' => $ref,
                'status' => $paid + 0.01 >= (float) $e->raised + (float) $e->fund_contribution ? 'paid' : $e->status]);
        });
        AuditLog::record('welfare_payout', $e, ['amount' => $amount, 'channel' => $channel]);
        $this->notify->toMember($e->member, 'Dear {first_name}, KES ' . number_format($amount, 2) . " has been paid to you from the welfare fund ({$e->title}). Our thoughts are with you. - " . sacco_name());
    }

    /** SMS everyone with unpaid dues for this event. */
    public function remindDue(WelfareEvent $e): int
    {
        $n = 0;
        foreach ($e->contributions()->where('status', 'due')->with('member')->get() as $c) {
            $this->notify->toMember($c->member, 'Dear {first_name}, your welfare contribution of KES ' . number_format($c->amount) . " for {$e->title} is pending. Pay via Paybill "
                . config('sacco.mpesa.paybill') . ", Acc {member_no}-W{$e->id}. Thank you.");
            $n++;
        }
        return $n;
    }

    /** Monthly welfare contribution (setting welfare_monthly_amount, 0 = off). */
    public function chargeMonthly(?string $period = null): array
    {
        $amount = (float) Setting::get('welfare_monthly_amount', 0);
        $period ??= now()->format('Y-m');
        $out = ['charged' => 0, 'deducted' => 0, 'due' => 0];
        if ($amount <= 0) return $out;
        $source = Setting::get('welfare_monthly_source', 'SAV');
        Member::active()->chunkById(200, function ($members) use ($amount, $period, $source, &$out) {
            foreach ($members as $m) {
                $c = WelfareContribution::firstOrCreate(['uniq_key' => "M{$period}-{$m->id}"], [
                    'member_id' => $m->id, 'amount' => $amount, 'kind' => 'monthly', 'status' => 'due', 'period' => $period,
                ]);
                if ($c->wasRecentlyCreated) $out['charged']++;
                if ($c->status === 'due') $this->tryDeduct($c, $source, null) ? $out['deducted']++ : $out['due']++;
            }
        });
        return $out;
    }

    public function refresh(WelfareEvent $e): void
    {
        $e->update(['raised' => (float) $e->contributions()->where('status', 'paid')->sum('amount')]);
    }
}
