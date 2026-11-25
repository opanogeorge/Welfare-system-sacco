<?php

namespace Database\Seeders;

use App\Models\AccountType;
use App\Models\LoanProduct;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(['email' => 'admin@sacco.local'], [
            'name' => 'System Administrator', 'role' => 'admin', 'is_active' => true,
            'password' => Hash::make('Admin@123'),
        ]);

        $this->call(ChartOfAccountsSeeder::class);

        foreach ([
            ['code' => 'SHR', 'name' => 'Share Capital', 'withdrawable' => false, 'counts_for_loan' => false, 'earns_dividend' => true,  'earns_interest' => false, 'min_balance' => 0, 'withdrawal_fee' => 0],
            ['code' => 'DEP', 'name' => 'Member Deposits (BOSA)', 'withdrawable' => false, 'counts_for_loan' => true, 'earns_dividend' => false, 'earns_interest' => true, 'min_balance' => 0, 'withdrawal_fee' => 0],
            ['code' => 'SAV', 'name' => 'Savings Account (FOSA)', 'withdrawable' => true, 'counts_for_loan' => false, 'earns_dividend' => false, 'earns_interest' => false, 'min_balance' => 500, 'withdrawal_fee' => 30],
        ] as $t) {
            $t['gl_account_id'] = \App\Models\GlAccount::where('system_key', 'acct_' . $t['code'])->value('id');
            AccountType::updateOrCreate(['code' => $t['code']], $t);
        }
        AccountType::where('code', 'SAV')->update(['interest_rate' => 3]);

        foreach (['Staff', 'Associate', 'Retiree', 'Group / Corporate'] as $c) {
            \App\Models\MemberCategory::firstOrCreate(['name' => $c]);
        }
        foreach (['bank' => 'Main Bank Account', 'mpesa' => 'M-Pesa Paybill'] as $key => $name) {
            \App\Models\BankAccount::firstOrCreate(['name' => $name], ['gl_account_id' => \App\Models\GlAccount::where('system_key', $key)->value('id')]);
        }

        foreach ([
            ['code' => 'DEV', 'name' => 'Development Loan', 'interest_rate' => 12, 'interest_method' => 'reducing', 'min_period' => 6, 'max_period' => 60, 'min_amount' => 10000, 'max_amount' => 3000000, 'deposit_multiplier' => 3, 'processing_fee_rate' => 1, 'insurance_rate' => 0.5, 'penalty_rate' => 2, 'min_guarantors' => 2, 'min_membership_months' => 6],
            ['code' => 'EMG', 'name' => 'Emergency Loan', 'interest_rate' => 12, 'interest_method' => 'flat', 'min_period' => 1, 'max_period' => 12, 'min_amount' => 2000, 'max_amount' => 200000, 'deposit_multiplier' => 1, 'processing_fee_rate' => 0, 'insurance_rate' => 0, 'penalty_rate' => 2, 'min_guarantors' => 0, 'min_membership_months' => 3],
            ['code' => 'SCH', 'name' => 'School Fees Loan', 'interest_rate' => 10, 'interest_method' => 'reducing', 'min_period' => 1, 'max_period' => 12, 'min_amount' => 5000, 'max_amount' => 500000, 'deposit_multiplier' => 2, 'processing_fee_rate' => 0.5, 'insurance_rate' => 0, 'penalty_rate' => 2, 'min_guarantors' => 1, 'min_membership_months' => 3],
            ['code' => 'ADV', 'name' => 'Salary Advance', 'interest_rate' => 60, 'interest_method' => 'flat', 'min_period' => 1, 'max_period' => 1, 'min_amount' => 1000, 'max_amount' => 50000, 'deposit_multiplier' => 1, 'processing_fee_rate' => 0, 'insurance_rate' => 0, 'penalty_rate' => 5, 'min_guarantors' => 0, 'min_membership_months' => 1],
        ] as $p) {
            LoanProduct::updateOrCreate(['code' => $p['code']], $p + ['is_active' => true]);
        }

        foreach ([
            'sacco_name' => config('sacco.name'), 'member_prefix' => 'MBR', 'registration_fee' => 1000,
            'min_share_capital' => 5000, 'penalty_grace_days' => 5, 'dormant_months' => 6, 'disbursement_account' => 'SAV',
            'approval_level2_threshold' => 500000, 'approval_level3_threshold' => 2000000, 'top_up_min_paid_percent' => 50,
            'fd_min_amount' => 10000, 'fd_wht_rate' => 15, 'fd_break_rate_factor' => 0.5, 'aml_cash_threshold' => 1000000,
            'require_id_document' => 0, 'staff_2fa' => 0, 'member_2fa' => 0, 'password_expiry_days' => 90,
            'online_onboarding' => 1, 'auto_iprs_check' => 1, 'min_monthly_contribution' => 500, 'bylaws_version' => 1, 'loan_terms_version' => 1,
            'require_crb_check' => 0, 'write_off_min_days' => 180, 'guarantor_registration_required' => 0, 'guarantor_deposit_multiple' => 1, 'guarantor_max_amount' => 0, 'guarantor_max_loans' => 5,
            'guarantor_min_months' => 6, 'guarantor_max_arrears_days' => 30, 'guarantor_allow_mutual' => 1, 'guarantor_request_days' => 7, 'welfare_monthly_amount' => 0, 'welfare_monthly_day' => 1, 'welfare_monthly_source' => 'SAV', 'welfare_requests_from_portal' => 1, 'theme' => 'navy', 'demand_notice_days' => 14, 'risk_min_samples' => 50, 'risk_min_defaults' => 5, 'risk_auto_recommend_approve' => 0.07, 'risk_decline_above' => 0.30, 'risk_lgd' => 0.45,
            'fraud_velocity_24h' => 10, 'fraud_rapid_min' => 50000, 'fraud_dormant_min' => 50000, 'fraud_anomaly_z' => 4, 'fraud_anomaly_min' => 20000,
            'fraud_work_start' => 7, 'fraud_work_end' => 19, 'fraud_backdate_days' => 3, 'fraud_reversals_week' => 5, 'fraud_max_guarantees' => 5,
        ] as $k => $v) {
            if (Setting::get($k) === null) Setting::put($k, $v);
        }
    }
}
