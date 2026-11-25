<?php

namespace Database\Seeders;

use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\User;
use App\Services\AccountService;
use App\Services\LoanService;
use App\Services\NumberGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/** php artisan db:seed --class=DemoSeeder   (test data only - do not run in production) */
class DemoSeeder extends Seeder
{
    public function run(AccountService $accounts, LoanService $loans): void
    {
        $admin = User::where('role', 'admin')->first();
        $hq = \App\Models\Branch::firstOrCreate(['code' => 'HQ'], ['name' => 'Head Office', 'location' => 'Kisumu', 'is_hq' => true]);
        \App\Models\Branch::firstOrCreate(['code' => 'SYA'], ['name' => 'Siaya Branch', 'location' => 'Siaya']);
        auth()->setUser($admin);

        foreach (['manager' => 'Mary Manager', 'loan_officer' => 'Leo Officer', 'teller' => 'Tina Teller', 'accountant' => 'Alex Accountant'] as $role => $name) {
            User::updateOrCreate(['email' => "$role@sacco.local"], ['name' => $name, 'role' => $role, 'password' => Hash::make('Password1'), 'is_active' => true]);
        }

        $names = [['Jane', 'Achieng', 'Otieno'], ['Peter', 'Ouma', 'Okoth'], ['Mercy', 'Wanjiru', 'Kamau'], ['John', 'Kiprop', 'Rotich'], ['Grace', 'Atieno', 'Odhiambo']];
        $members = [];
        foreach ($names as $i => [$f, $m, $l]) {
            $member = Member::create([
                'member_no' => NumberGenerator::memberNo(), 'first_name' => $f, 'middle_name' => $m, 'last_name' => $l,
                'national_id' => (string) (30000000 + $i), 'phone' => '07' . rand(10000000, 99999999),
                'gender' => $i % 2 ? 'male' : 'female', 'employer' => 'Demo Employer', 'payroll_no' => 'P' . (1000 + $i),
                'monthly_contribution' => 3000, 'joined_on' => now()->subMonths(18), 'status' => 'active',
            ]);
            $accounts->openDefaultAccounts($member);
            $user = User::create(['name' => $member->full_name, 'email' => strtolower($member->member_no) . '@members.local',
                'phone' => $member->phone, 'role' => 'member', 'password' => Hash::make($member->national_id)]);
            $member->update(['user_id' => $user->id]);

            $accounts->deposit($member, 'SHR', 5000, ['date' => now()->subMonths(18)]);
            for ($mo = 17; $mo >= 0; $mo--) {
                $accounts->deposit($member, 'DEP', 3000, ['channel' => 'payroll', 'date' => now()->subMonths($mo)->startOfMonth()]);
            }
            $accounts->deposit($member, 'SAV', 2000 * ($i + 1), ['channel' => 'mpesa']);
            $members[] = $member;
        }

        // A running development loan for the first member, guaranteed by two others
        $loan = $loans->apply($members[0], LoanProduct::where('code', 'DEV')->first(), 100000, 24, 'Home improvement', [
            ['member_id' => $members[1]->id, 'amount' => 30000],
            ['member_id' => $members[2]->id, 'amount' => 30000],
        ]);
        foreach ($loan->guarantors as $g) $loans->respondGuarantee($g, true);
        $loan->refresh();
        $loans->appraise($loan, User::where('role', 'loan_officer')->first(), 'Payslip verified.');
        $loans->approve($loan->refresh(), User::where('role', 'manager')->first(), 100000, 'Approved by credit committee.');
        $loans->disburse($loan->refresh(), $admin, 'savings', now()->subMonths(3), now()->subMonths(2));
        $loans->repay($loan->refresh(), 4707.35, 'payroll', 'CHK-DEMO', now()->subMonths(2));

        // A pending emergency loan for the second member
        $loans->apply($members[3], LoanProduct::where('code', 'EMG')->first(), 20000, 6, 'Medical bill');
    }
}
