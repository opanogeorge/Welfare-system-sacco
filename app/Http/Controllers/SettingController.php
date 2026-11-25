<?php

namespace App\Http\Controllers;

use App\Models\AccountType;
use App\Models\Member;
use App\Models\Setting;
use App\Services\AccountService;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public const KEYS = [
        'sacco_name'           => 'SACCO name',
        'sacco_address'        => 'Postal address',
        'sacco_phone'          => 'Phone',
        'sacco_email'          => 'Email',
        'member_prefix'        => 'Member number prefix',
        'registration_fee'     => 'Registration fee (KES)',
        'min_share_capital'    => 'Minimum share capital (KES)',
        'penalty_grace_days'   => 'Penalty grace period (days)',
        'dormant_months'       => 'Months without deposits before dormant',
        'disbursement_account' => 'Account code loans are disbursed to',
        'approval_level2_threshold' => 'Loans above this need a 2nd approver (KES, 0 = off)',
        'approval_level3_threshold' => 'Loans above this need Board Chair approval (KES, 0 = off)',
        'top_up_min_paid_percent' => 'Min % of principal repaid before a top-up',
        'fd_min_amount'        => 'Minimum fixed deposit (KES)',
        'fd_wht_rate'          => 'WHT on fixed-deposit interest (%)',
        'fd_break_rate_factor' => 'Early FD break: share of rate paid (0-1)',
        'aml_cash_threshold'   => 'AML: flag cash transactions from (KES)',
        'require_id_document'  => 'Require ID document before approval (1/0)',
        'staff_2fa'            => 'Two-factor login (OTP) for staff (1/0)',
        'member_2fa'           => 'Two-factor login (OTP) for members (1/0)',
        'staff_allowed_ips'    => 'Staff allowed IPs/ranges (comma list, blank = any)',
        'password_expiry_days' => 'Staff password expiry (days, 0 = never)',
        'books_locked_until'   => 'Books locked up to (YYYY-MM-DD)',
        // Digital onboarding & e-signatures
        'online_onboarding'    => 'Allow online applications at /join (1/0)',
        'auto_iprs_check'      => 'Run IPRS check automatically on online applications (1/0)',
        'min_monthly_contribution' => 'Minimum monthly contribution for new members (KES)',
        'bylaws_version'       => 'By-laws version (bump when by-laws change)',
        'loan_terms_version'   => 'Loan terms version (bump when terms change)',
        // Credit risk & CRB
        'guarantor_registration_required' => 'Guarantors must register first (1/0)',
        'guarantor_deposit_multiple' => 'Guarantee limit = deposits × (e.g. 1)',
        'guarantor_max_amount' => 'Max total a member can guarantee (KES, 0 = no cap)',
        'guarantor_max_loans' => 'Max loans one member can guarantee at a time (0 = no limit)',
        'guarantor_min_months' => 'Membership months before one can guarantee',
        'guarantor_max_arrears_days' => 'Block guarantors whose own loan is this many days late',
        'guarantor_allow_mutual' => 'Allow members to guarantee each other (1/0)',
        'guarantor_request_days' => 'Days a guarantor has to respond before the request lapses',
        'welfare_monthly_amount' => 'Monthly welfare contribution per member (KES, 0 = off)',
        'welfare_monthly_day'  => 'Day of the month to charge the welfare contribution',
        'welfare_monthly_source' => 'Deduct monthly welfare from (SAV or DEP)',
        'welfare_requests_from_portal' => 'Members can report welfare events in the portal (1/0)',
        'write_off_min_days'   => 'Min days in arrears before a loan can be written off',
        'demand_notice_days'   => 'Days given to pay in a demand letter',
        'require_crb_check'    => 'Require a CRB check (last 30 days) before loan approval (1/0)',
        'risk_min_samples'     => 'AI model: minimum finished loans to train',
        'risk_min_defaults'    => 'AI model: minimum defaults to train',
        'risk_auto_recommend_approve' => 'AI model: recommend approve below PD (0-1)',
        'risk_decline_above'   => 'AI model: recommend decline above PD (0-1)',
        'risk_lgd'             => 'Loss given default for expected loss (0-1)',
        // Fraud monitoring thresholds
        'fraud_velocity_24h'   => 'Fraud: max transactions per member in 24h',
        'fraud_rapid_min'      => 'Fraud: rapid in-out minimum withdrawal (KES)',
        'fraud_dormant_min'    => 'Fraud: dormant-account alert from (KES)',
        'fraud_anomaly_z'      => 'Fraud: amount anomaly – standard deviations',
        'fraud_anomaly_min'    => 'Fraud: amount anomaly minimum (KES)',
        'fraud_work_start'     => 'Fraud: working hours start (hour, 0-23)',
        'fraud_work_end'       => 'Fraud: working hours end (hour, 0-23)',
        'fraud_backdate_days'  => 'Fraud: back-dating allowed (days)',
        'fraud_reversals_week' => 'Fraud: reversals per user per week',
        'fraud_max_guarantees' => 'Fraud: max running loans one member guarantees',
    ];

    public function edit()
    {
        $values = collect(self::KEYS)->mapWithKeys(fn ($l, $k) => [$k => Setting::get($k)]);
        return view('admin.settings', ['keys' => self::KEYS, 'values' => $values, 'types' => AccountType::with('glAccount')->get(),
            'categories' => \App\Models\MemberCategory::withCount('members')->get(),
            'glAccounts' => \App\Models\GlAccount::whereIn('type', ['liability', 'equity'])->orderBy('code')->get()]);
    }

    public function storeCategory(Request $request)
    {
        \App\Models\MemberCategory::create($request->validate(['name' => 'required|string|max:80', 'description' => 'nullable|string|max:255']));
        return back()->with('success', 'Category added.');
    }

    public function update(Request $request)
    {
        foreach (array_keys(self::KEYS) as $k) {
            Setting::put($k, $request->input($k));
        }
        if ($request->filled('theme') && array_key_exists($request->theme, theme_presets())) Setting::put('theme', $request->theme);
        return back()->with('success', 'Settings saved.');
    }

    public function storeAccountType(Request $request, AccountService $accounts)
    {
        $type = AccountType::create($this->validated($request));
        // open the new account for every existing member
        Member::whereIn('status', ['active', 'dormant', 'pending'])->each(fn ($m) => $accounts->openDefaultAccounts($m));
        return back()->with('success', "Account type {$type->name} created and opened for all members.");
    }

    public function updateAccountType(Request $request, AccountType $accountType)
    {
        $accountType->update($this->validated($request, $accountType));
        return back()->with('success', 'Account type updated.');
    }

    private function validated(Request $request, ?AccountType $t = null): array
    {
        $data = $request->validate([
            'code' => 'required|string|max:20|alpha_num|unique:account_types,code' . ($t ? ",{$t->id}" : ''),
            'name' => 'required|string|max:80',
            'min_balance' => 'required|numeric|min:0',
            'withdrawal_fee' => 'required|numeric|min:0',
            'interest_rate' => 'nullable|numeric|min:0|max:30',
            'monthly_minimum' => 'nullable|numeric|min:0',
            'gl_account_id' => 'nullable|exists:gl_accounts,id',
        ]);
        $data['interest_rate'] = $data['interest_rate'] ?? 0;
        $data['monthly_minimum'] = $data['monthly_minimum'] ?? 0;
        $data['code'] = strtoupper($data['code']);
        foreach (['withdrawable', 'counts_for_loan', 'earns_dividend', 'earns_interest', 'is_active'] as $f) {
            $data[$f] = $request->boolean($f);
        }
        return $data;
    }
}
