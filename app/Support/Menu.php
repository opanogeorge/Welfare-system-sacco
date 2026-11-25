<?php

namespace App\Support;

use App\Models\FraudAlert;
use App\Models\LoanGuarantor;
use App\Models\MemberChangeRequest;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Illuminate\Support\Facades\Route;

/**
 * Sidebar definition. A group with one visible item renders as a plain link;
 * otherwise it renders as a collapsible main menu with sub-items.
 * Item: [label, route, icon, activePatterns[], visible(bool), badge(int|null)]
 */
class Menu
{
    public static function for(User $u): array
    {
        $groups = $u->role === 'member' ? self::member($u) : self::staff($u);

        // drop hidden items, missing routes and empty groups; work out active + badges
        return collect($groups)->map(function ($g) use ($u) {
            $items = collect($g['items'])->filter(fn ($i) => ($i[4] ?? true) && Route::has($i[1]) && $u->canRoute($i[1]))->map(fn ($i) => [
                'label' => $i[0], 'url' => route($i[1]), 'icon' => $i[2],
                'active' => request()->routeIs(...($i[3] ?? [$i[1]])), 'badge' => $i[5] ?? null,
            ])->values();
            return $items->isEmpty() ? null : $g + [
                'list' => $items, 'active' => $items->contains('active', true), 'badge' => $items->sum('badge') ?: null,
            ];
        })->filter()->values()->all();
    }

    private static function member(User $u): array
    {
        $pendingGuarantees = $u->member ? LoanGuarantor::where('member_id', $u->member->id)->where('status', 'pending')->count() : 0;
        $welfareDue = $u->member ? \App\Models\WelfareContribution::where('member_id', $u->member->id)->where('status', 'due')->count() : 0;
        return [
            ['key' => 'home', 'label' => 'My Account', 'icon' => 'house', 'items' => [
                ['My Account', 'portal.home', 'house', ['portal.home']],
            ]],
            ['key' => 'savings', 'label' => 'Savings', 'icon' => 'piggy-bank', 'items' => [
                ['Deposit / pay (M-Pesa)', 'portal.pay', 'phone'],
                ['Contribution history', 'portal.contributions', 'calendar-check'],
                ['Statement & download', 'portal.statement', 'file-earmark-text', ['portal.statement*']],
                ['Request withdrawal', 'portal.withdrawals', 'wallet2'],
                ['Dividends', 'portal.dividends', 'percent'],
            ]],
            ['key' => 'loans', 'label' => 'Loans', 'icon' => 'cash-coin', 'items' => [
                ['My loans & applications', 'portal.loans', 'list-check', ['portal.loans', 'portal.loans.show']],
                ['Apply for a loan', 'portal.loans.apply', 'plus-circle'],
                ['Guarantor obligations', 'portal.guarantees', 'people', ['portal.guarantees'], true, $pendingGuarantees],
                ['Loan calculator', 'loans.calculator', 'calculator'],
            ]],
            ['key' => 'welfare', 'label' => 'Welfare', 'icon' => 'heart-pulse', 'items' => [
                ['Welfare & support', 'portal.welfare', 'heart-pulse', ['portal.welfare'], true, $welfareDue ?: null],
            ]],
            ['key' => 'account', 'label' => 'My Profile', 'icon' => 'person-circle', 'items' => [
                ['Personal information', 'portal.profile', 'person-vcard'],
                ['Change password', 'profile.password', 'key'],
                ['Help & complaints', 'portal.tickets', 'chat-dots'],
            ]],
        ];
    }

    private static function staff(User $u): array
    {
        // Item visibility comes from permissions (canRoute() in for()); these only add extra conditions.
        $cash = $mgr = $acct = true;
        $hqFin = $u->isHq();   // HQ-only screens (dividends, accounting)

        return [
            ['key' => 'dash', 'label' => 'Dashboard', 'icon' => 'speedometer2', 'items' => [
                ['Dashboard', 'dashboard', 'speedometer2'],
            ]],
            ['key' => 'members', 'label' => 'Members', 'icon' => 'people', 'items' => [
                ['All members', 'members.index', 'people', ['members.index', 'members.show', 'members.edit', 'members.statement*', 'members.identity', 'members.exit']],
                ['Register member', 'members.create', 'person-plus'],
                ['Profile updates', 'service.changes', 'person-check', ['service.changes'], true, MemberChangeRequest::where('status', 'pending')->count()],
                ['Complaints & inquiries', 'service.tickets', 'chat-dots', ['service.tickets'], true, Ticket::whereIn('status', ['open', 'in_progress'])->count()],
            ]],
            ['key' => 'cash', 'label' => 'Cash Office', 'icon' => 'cash-stack', 'items' => [
                ['Deposit / withdraw', 'transactions.create', 'plus-circle', ['transactions.create', 'transactions.receipt'], $cash],
                ['Transactions', 'transactions.index', 'journal-text', ['transactions.index'], $cash],
                ['Withdrawal requests', 'service.withdrawals', 'wallet2', ['service.withdrawals'], $cash, $cash ? WithdrawalRequest::where('status', 'pending')->count() : null],
                ['M-Pesa payments', 'mpesa.index', 'phone', ['mpesa.*'], $cash],
                ['Bulk posting (CSV)', 'checkoff.create', 'upload', ['checkoff.create'], $cash],
                ['Employers & check-off', 'employers.index', 'building', ['employers.*', 'checkoff.schedules.*'], $cash],
            ]],
            ['key' => 'savings', 'label' => 'Savings', 'icon' => 'piggy-bank', 'items' => [
                ['Fixed deposits', 'savings.fd', 'safe', ['savings.fd*'], $cash],
                ['Standing orders', 'savings.so', 'arrow-repeat', ['savings.so*'], $cash],
                ['Contribution arrears', 'savings.arrears', 'calendar-x'],
            ]],
            ['key' => 'loans', 'label' => 'Loans', 'icon' => 'cash-coin', 'items' => [
                ['All loans', 'loans.index', 'list-ul', ['loans.index', 'loans.show', 'loans.statement']],
                ['New loan application', 'loans.create', 'plus-circle', ['loans.create'], true],
                ['Guarantor management', 'guarantors.index', 'people', ['guarantors.*'], true, \App\Models\LoanGuarantor::whereNotNull('release_requested_at')->where('status', 'accepted')->count() ?: null],
                ['Automatic reminders', 'reminders.index', 'alarm', ['reminders.*'], true],
                ['Loan recovery', 'loans.recovery', 'exclamation-octagon', ['loans.recovery', 'loans.demand-letter'], true],
                ['CRB checks', 'crb.index', 'clipboard-data', ['crb.*'], true],
                ['Loan products', 'loan-products.index', 'box', ['loan-products.*'], $mgr],
                ['Loan calculator', 'loans.calculator', 'calculator'],
            ]],
            ['key' => 'welfare', 'label' => 'Welfare', 'icon' => 'heart-pulse', 'items' => [
                ['Welfare events', 'welfare.index', 'heart-pulse', ['welfare.index', 'welfare.show', 'welfare.edit'], true, \App\Models\WelfareEvent::where('status', 'requested')->count() ?: null],
                ['New welfare event', 'welfare.create', 'plus-circle'],
                ['Benefits & monthly fund', 'welfare.benefits', 'card-checklist'],
            ]],
            ['key' => 'finance', 'label' => 'Finance', 'icon' => 'bank', 'items' => [
                ['Accounting & GL', 'accounting.trial-balance', 'calculator-fill', ['accounting.*'], $hqFin],
                ['Dividends', 'dividends.index', 'percent', ['dividends.*'], $hqFin],
                ['Expenses & payables', 'expenses.index', 'receipt', ['expenses.*'], $acct],
                ['Fixed assets', 'assets.index', 'pc-display', ['assets.*'], $acct],
            ]],
            ['key' => 'risk', 'label' => 'Risk & Compliance', 'icon' => 'shield-check', 'items' => [
                ['Credit risk (AI)', 'risk.index', 'cpu', ['risk.*'], true],
                ['Fraud monitoring', 'fraud.index', 'shield-exclamation', ['fraud.*'], true,
                    $u->hasPermission('fraud.view') ? FraudAlert::whereIn('status', ['open', 'investigating'])->count() : null],
                ['AML alerts', 'reports.aml', 'incognito', ['reports.aml'], $mgr],
                ['Audit trail', 'reports.audit', 'clock-history', ['reports.audit', 'reports.logins'], $mgr],
            ]],
            ['key' => 'reports', 'label' => 'Reports & Analytics', 'icon' => 'bar-chart', 'items' => [
                ['Analytics (BI)', 'analytics', 'graph-up-arrow', ['analytics'], true],
                ['All reports', 'reports.index', 'bar-chart', ['reports.index', 'reports.portfolio', 'reports.arrears', 'reports.balances', 'reports.cashbook', 'reports.income', 'reports.guarantors', 'reports.register', 'reports.profitability'], true],
            ]],
            ['key' => 'gov', 'label' => 'Governance', 'icon' => 'diagram-3', 'items' => [
                ['Board & meetings', 'governance.index', 'diagram-3', ['governance.*'], $mgr],
                ['Bulk messaging', 'messaging.index', 'megaphone', ['messaging.*'], $mgr],
            ]],
            ['key' => 'admin', 'label' => 'Administration', 'icon' => 'gear', 'items' => [
                ['Staff users', 'admin.users.index', 'person-gear', ['admin.users.*']],
                ['Roles & permissions', 'admin.roles.index', 'shield-lock', ['admin.roles.*']],
                ['Branches', 'admin.branches', 'diagram-2', ['admin.branches']],
                ['Settings', 'admin.settings', 'sliders', ['admin.settings']],
                ['Integrations', 'admin.integrations', 'plug', ['admin.integrations']],
                ['API & webhooks', 'admin.api', 'braces', ['admin.api*']],
            ]],
        ];
    }
}
