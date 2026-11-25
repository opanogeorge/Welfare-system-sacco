<?php

namespace Database\Seeders;

use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\Member;
use Illuminate\Database\Seeder;

/**
 * DEMO ONLY - synthetic finished loans so you can see the AI credit-risk model train and score.
 *   php artisan db:seed --class=DemoRiskHistorySeeder
 *   then Credit Risk (AI) > Train model now
 * These rows have no transactions or journals; never run this on a live database.
 */
class DemoRiskHistorySeeder extends Seeder
{
    public function run(): void
    {
        $members = Member::pluck('id');
        $product = LoanProduct::where('code', 'DEV')->first() ?? LoanProduct::first();
        if ($members->isEmpty() || ! $product) {
            $this->command?->warn('Run DemoSeeder first.');
            return;
        }
        mt_srand(42);
        for ($i = 1; $i <= 160; $i++) {
            $tenure = mt_rand(3, 120); $savingMonths = mt_rand(2, 12); $debt = mt_rand(0, 250) / 100; $ltd = mt_rand(30, 300) / 100;
            $pastArrears = mt_rand(0, 3) ? 0 : mt_rand(10, 120); $cleared = mt_rand(0, 6); $period = [6, 12, 24, 36, 48][mt_rand(0, 4)];
            $instRatio = mt_rand(50, 400) / 100; $cover = mt_rand(0, 150) / 100;
            // "True" risk the model should discover
            $z = -2.4 + 0.9 * $debt + 0.6 * $ltd + 0.025 * $pastArrears - 0.02 * $tenure - 0.18 * $savingMonths + 0.35 * $instRatio - 0.6 * $cover - 0.15 * $cleared;
            $default = (mt_rand() / mt_getrandmax()) < 1 / (1 + exp(-$z));
            $amount = mt_rand(20, 300) * 1000;
            $start = now()->subMonths($period + mt_rand(1, 24));
            Loan::create([
                'loan_no' => 'DEMOH' . str_pad((string) $i, 5, '0', STR_PAD_LEFT), 'member_id' => $members->random(), 'loan_product_id' => $product->id,
                'amount_applied' => $amount, 'amount_approved' => $amount, 'interest_rate' => $product->interest_rate, 'interest_method' => $product->interest_method,
                'period' => $period, 'status' => $default ? (mt_rand(0, 1) ? 'written_off' : 'closed') : 'closed',
                'applied_on' => $start, 'disbursed_on' => $start, 'maturity_date' => $start->copy()->addMonths($period), 'closed_on' => $start->copy()->addMonths($period),
                'max_days_arrears' => $default ? mt_rand(91, 400) : mt_rand(0, 25), 'purpose' => 'Demo history',
                'risk_features' => [
                    'tenure_months' => $tenure, 'saving_months' => $savingMonths, 'log_deposits' => log10(max(1, $amount / $ltd)),
                    'loan_to_deposits' => $ltd, 'debt_to_deposits' => $debt, 'installment_ratio' => $instRatio, 'past_max_arrears' => $pastArrears,
                    'loans_cleared' => $cleared, 'period' => $period, 'age' => mt_rand(22, 60), 'guarantor_cover' => $cover, 'is_top_up' => mt_rand(0, 4) ? 0 : 1,
                ],
            ]);
        }
        $this->command?->info('160 synthetic finished loans created. Open Credit Risk (AI) and click "Train model now".');
    }
}
