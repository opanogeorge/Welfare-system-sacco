<?php

namespace Database\Seeders;

use App\Models\GlAccount;
use Illuminate\Database\Seeder;

/**
 * Default SACCO chart of accounts. `system_key` links accounts used by automatic posting.
 * You can rename accounts and add sub-accounts freely; don't delete or re-key the system ones.
 */
class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            // code, name, type, system_key, is_cash
            ['1000', 'Cash on Hand', 'asset', 'cash', true],
            ['1010', 'Bank - Main Account', 'asset', 'bank', true],
            ['1020', 'M-Pesa Paybill', 'asset', 'mpesa', true],
            ['1100', 'Loans to Members', 'asset', 'loans_receivable', false],
            ['1110', 'Loan Loss Provision', 'asset', 'loan_loss_provision', false],
            ['1150', 'Check-off Receivable (Employers)', 'asset', 'checkoff_receivable', false],
            ['1190', 'Internal Clearing / Suspense', 'asset', 'clearing', false],
            ['1200', 'Investments', 'asset', 'investments', false],
            ['1300', 'Prepayments & Other Receivables', 'asset', 'receivables', false],
            ['1500', 'Property & Equipment - Cost', 'asset', 'fixed_assets', false],
            ['1510', 'Accumulated Depreciation', 'asset', 'accumulated_depreciation', false],

            ['2010', 'Member Deposits (BOSA)', 'liability', 'acct_DEP', false],
            ['2020', 'Member Savings (FOSA)', 'liability', 'acct_SAV', false],
            ['2030', 'Member Fixed Deposits', 'liability', 'fixed_deposits', false],
            ['2100', 'Loan Insurance Premiums Payable', 'liability', 'insurance_payable', false],
            ['2110', 'Withholding Tax Payable', 'liability', 'wht_payable', false],
            ['2120', 'Statutory Deductions Payable (PAYE/NSSF/SHIF)', 'liability', 'statutory_payable', false],
            ['2200', 'Accounts Payable', 'liability', 'payables', false],
            ['2300', 'Accrued Expenses', 'liability', 'accruals', false],
            ['2400', 'Welfare / Benevolent Fund', 'liability', 'welfare_fund', false],

            ['3010', 'Share Capital', 'equity', 'acct_SHR', false],
            ['3100', 'Retained Earnings', 'equity', 'retained_earnings', false],
            ['3200', 'Statutory Reserve', 'equity', 'statutory_reserve', false],
            ['3300', 'Dividends & Interest on Deposits (Appropriation)', 'equity', 'dividend_appropriation', false],
            ['3900', 'Opening Balances (Go-live)', 'equity', 'opening_balances', false],

            ['4010', 'Interest on Loans', 'income', 'interest_income', false],
            ['4020', 'Penalty Income', 'income', 'penalty_income', false],
            ['4030', 'Loan Processing Fees', 'income', 'processing_fee_income', false],
            ['4040', 'Registration Fees', 'income', 'registration_fee_income', false],
            ['4050', 'Transaction & Withdrawal Fees', 'income', 'fee_income', false],
            ['4060', 'Investment Income', 'income', 'investment_income', false],
            ['4070', 'Bad Debts Recovered', 'income', 'bad_debt_recovered', false],
            ['4090', 'Other Income', 'income', 'other_income', false],

            ['5010', 'Salaries & Wages', 'expense', null, false],
            ['5020', 'Rent & Rates', 'expense', null, false],
            ['5030', 'Electricity & Water', 'expense', null, false],
            ['5040', 'Printing & Stationery', 'expense', null, false],
            ['5050', 'Travel & Transport', 'expense', null, false],
            ['5060', 'AGM & Board Meeting Expenses', 'expense', null, false],
            ['5070', 'Bank Charges', 'expense', 'bank_charges', false],
            ['5080', 'Audit & Professional Fees', 'expense', null, false],
            ['5090', 'Software, ICT & Internet', 'expense', null, false],
            ['5095', 'SMS & Communication', 'expense', null, false],
            ['5100', 'Depreciation', 'expense', 'depreciation_expense', false],
            ['5110', 'Interest on Savings & Fixed Deposits', 'expense', 'savings_interest_expense', false],
            ['5120', 'Loan Loss Provision Expense', 'expense', 'provision_expense', false],
            ['5130', 'SASRA Levy & Licences', 'expense', null, false],
            ['5140', 'Member Education & Training', 'expense', null, false],
            ['5900', 'Other Expenses', 'expense', 'other_expense', false],
        ];

        foreach ($accounts as [$code, $name, $type, $key, $cash]) {
            GlAccount::updateOrCreate(['code' => $code], ['name' => $name, 'type' => $type, 'system_key' => $key, 'is_cash' => $cash]);
        }
    }
}
