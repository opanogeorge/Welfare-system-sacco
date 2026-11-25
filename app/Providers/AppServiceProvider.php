<?php

namespace App\Providers;

use App\Models\LoanRepayment;
use App\Models\Transaction;
use App\Services\AccountingService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        require_once app_path('helpers.php');
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // Main database connection, used to look up SACCOs in multi-SACCO mode
        config(['database.connections.landlord' => config('database.connections.' . config('database.default'))]);

        // SMS / email / M-Pesa credentials saved in Admin > Integrations override .env.
        // (In multi-SACCO mode this happens per request, after the SACCO's database is selected.)
        if (! \App\Support\Tenancy::enabled()) {
            try {
                \App\Support\Integrations::apply();
            } catch (\Throwable) {
                // settings table not migrated yet
            }
        }

        // Real-time general-ledger posting. Runs inside the same DB transaction as the
        // member posting, so if the journal fails (e.g. books closed) the posting is rolled back.
        Transaction::created(fn (Transaction $tx) => app(AccountingService::class)->fromTransaction($tx));
        LoanRepayment::created(fn (LoanRepayment $r) => app(AccountingService::class)->fromRepayment($r));

        // Fraud / suspicious-activity rules and partner webhooks (never block a posting)
        Transaction::created(function (Transaction $tx) {
            app(\App\Services\FraudMonitor::class)->inspect($tx);
            if ($tx->member_account_id && $tx->type !== 'reversal') {
                app(\App\Services\WebhookService::class)->dispatch('transaction.posted', [
                    'reference' => $tx->reference, 'member_no' => $tx->member?->member_no, 'account' => $tx->account?->type?->code,
                    'type' => $tx->type, 'direction' => $tx->direction, 'amount' => (float) $tx->amount,
                    'balance' => (float) $tx->balance_after, 'channel' => $tx->channel, 'external_ref' => $tx->external_ref, 'date' => $tx->txn_date?->toDateString(),
                ]);
            }
        });
        LoanRepayment::created(fn (LoanRepayment $r) => app(\App\Services\WebhookService::class)->dispatch('loan.repaid', [
            'loan_no' => $r->loan->loan_no, 'member_no' => $r->loan->member->member_no, 'reference' => $r->reference, 'amount' => (float) $r->amount,
            'principal' => (float) $r->principal_paid, 'interest' => (float) $r->interest_paid, 'penalty' => (float) $r->penalty_paid,
            'balance' => $r->loan->fresh()->outstanding, 'channel' => $r->channel, 'date' => $r->paid_on->toDateString(),
        ]));
        \App\Models\Member::created(fn ($m) => app(\App\Services\WebhookService::class)->dispatch('member.created', [
            'member_no' => $m->member_no, 'name' => $m->full_name, 'status' => $m->status, 'channel' => $m->onboarding_channel ?? 'branch',
        ]));
    }
}
