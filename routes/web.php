<?php

use App\Http\Controllers\AccountingController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\ApiClientController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CrbController;
use App\Http\Controllers\FraudController;
use App\Http\Controllers\IdentityController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\RiskController;
use App\Http\Controllers\SignatureController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CheckoffController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DividendController;
use App\Http\Controllers\EmployerController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\GovernanceController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\LoanProductController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MessagingController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SavingsController;
use App\Http\Controllers\ServiceDeskController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\GuarantorController;
use App\Http\Controllers\WelfareController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() && auth()->user()->role === 'member' ? 'portal.home' : 'dashboard'));

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'login'])->middleware('throttle:10,1');
    Route::get('login/otp', [LoginController::class, 'otpForm'])->name('login.otp');
    Route::post('login/otp', [LoginController::class, 'verifyOtp'])->middleware('throttle:6,1');
});
Route::post('logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

/* ================= Public ================= */
Route::get('join', [OnboardingController::class, 'form'])->name('join');
Route::post('join/otp', [OnboardingController::class, 'sendOtp'])->name('join.otp')->middleware('throttle:10,1');
Route::post('join', [OnboardingController::class, 'submit'])->middleware('throttle:5,1');
Route::get('join/done', [OnboardingController::class, 'done'])->name('join.done');
Route::get('verify/{code?}', [SignatureController::class, 'verify'])->name('signatures.verify')->middleware('throttle:30,1');

Route::middleware(['auth', 'role'])->group(function () {
    Route::get('profile/password', [ProfileController::class, 'edit'])->name('profile.password');
    Route::put('profile/password', [ProfileController::class, 'update']);
    Route::get('loan-calculator', [LoanController::class, 'calculator'])->name('loans.calculator');
    Route::get('documents/{document}', [MemberController::class, 'document'])->name('documents.show');
    Route::get('loans/{loan}/agreement', [SignatureController::class, 'agreement'])->name('loans.agreement');
    Route::get('signatures/{signature}/image', [SignatureController::class, 'image'])->name('signatures.image');
});

/* ================= Member self-service portal ================= */
Route::middleware(['auth', 'role:member'])->prefix('portal')->name('portal.')->group(function () {
    Route::get('/', [PortalController::class, 'home'])->name('home');
    Route::get('statement', [PortalController::class, 'statement'])->name('statement');
    Route::get('contributions', [PortalController::class, 'contributions'])->name('contributions');
    Route::get('pay', [PortalController::class, 'pay'])->name('pay');
    Route::post('pay', [PortalController::class, 'payPush'])->middleware('throttle:5,1');
    Route::get('pay/status/{checkout}', [PortalController::class, 'payStatus'])->name('pay.status')->middleware('throttle:60,1');
    Route::get('loans/{loan}/statement', [PortalController::class, 'loanStatement'])->name('loans.statement');
    Route::get('statement/pdf', [PortalController::class, 'statementPdf'])->name('statement.pdf');
    Route::get('loans', [PortalController::class, 'loans'])->name('loans');
    Route::get('loans/apply', [PortalController::class, 'applyForm'])->name('loans.apply');
    Route::post('loans/apply', [PortalController::class, 'apply']);
    Route::get('loans/{loan}', [PortalController::class, 'loan'])->name('loans.show');
    Route::get('guarantees', [PortalController::class, 'guarantees'])->name('guarantees');
    Route::post('guarantees/{guarantor}', [PortalController::class, 'respond'])->name('guarantees.respond');
    Route::post('guarantees/{guarantor}/sign', [SignatureController::class, 'signGuarantee'])->name('guarantees.sign');
    Route::post('guarantees/{guarantor}/release', [PortalController::class, 'guaranteeRelease'])->name('guarantees.release')->middleware('throttle:5,1');
    Route::post('guarantor-register', [PortalController::class, 'guarantorRegister'])->name('guarantor.register');
    Route::post('loans/{loan}/guarantors', [PortalController::class, 'addGuarantor'])->name('loans.guarantors.add')->middleware('throttle:10,1');
    Route::post('loans/{loan}/sign', [SignatureController::class, 'signLoan'])->name('loans.sign');
    Route::get('withdrawals', [PortalController::class, 'withdrawals'])->name('withdrawals');
    Route::post('withdrawals', [PortalController::class, 'requestWithdrawal']);
    Route::get('profile', [PortalController::class, 'profile'])->name('profile');
    Route::post('profile', [PortalController::class, 'requestChange']);
    Route::get('dividends', [PortalController::class, 'dividends'])->name('dividends');
    Route::get('support', [PortalController::class, 'tickets'])->name('tickets');
    Route::post('support', [PortalController::class, 'storeTicket']);
    Route::get('member-lookup', [PortalController::class, 'lookup'])->name('lookup');
    Route::get('welfare', [PortalController::class, 'welfare'])->name('welfare');
    Route::post('welfare/request', [PortalController::class, 'welfareRequest'])->name('welfare.request')->middleware('throttle:5,1');
    Route::post('welfare/{event}/give', [PortalController::class, 'welfareGive'])->name('welfare.give')->middleware('throttle:10,1');
});

/* ================= Staff back-office ================= */
Route::middleware(['auth', 'role:staff', 'permission'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Members
    Route::get('members/lookup', [MemberController::class, 'lookup'])->name('members.lookup');
    Route::resource('members', MemberController::class)->except('destroy');
    Route::get('members/{member}/statement', [MemberController::class, 'statement'])->name('members.statement');
    Route::get('members/{member}/statement/pdf', [MemberController::class, 'statementPdf'])->name('members.statement.pdf');
    Route::post('members/{member}/status', [MemberController::class, 'status'])->name('members.status');
    Route::post('members/{member}/approve', [MemberController::class, 'approve'])->name('members.approve');
    Route::post('members/{member}/nominees', [MemberController::class, 'addNominee'])->name('members.nominees.store');
    Route::delete('nominees/{nominee}', [MemberController::class, 'removeNominee'])->name('members.nominees.destroy');
    Route::post('members/{member}/documents', [MemberController::class, 'uploadDocument'])->name('members.documents.store');
    Route::post('documents/{document}/verify', [MemberController::class, 'verifyDocument'])->name('documents.verify');
    Route::delete('documents/{document}', [MemberController::class, 'deleteDocument'])->name('documents.destroy');
    Route::get('members/{member}/exit', [MemberController::class, 'exitForm'])->name('members.exit');
    Route::post('members/{member}/exit', [MemberController::class, 'processExit'])->name('members.exit.store');
    Route::post('members/{member}/transfer-shares', [MemberController::class, 'transferShares'])->name('members.transfer-shares');
    Route::post('members/{member}/branch', [BranchController::class, 'transferMember'])->name('members.branch')->middleware('hq');

    // Identity verification (KYC)
    Route::get('members/{member}/identity', [IdentityController::class, 'show'])->name('members.identity');
    Route::post('members/{member}/identity/iprs', [IdentityController::class, 'iprs'])->name('members.identity.iprs');
    Route::post('members/{member}/identity/face', [IdentityController::class, 'face'])->name('members.identity.face');
    Route::post('members/{member}/identity/capture', [IdentityController::class, 'capture'])->name('members.identity.capture');

    // Credit reference bureau
    Route::get('crb', [CrbController::class, 'index'])->name('crb.index');
    Route::post('members/{member}/crb', [CrbController::class, 'run'])->name('crb.run');
    Route::post('members/{member}/crb/manual', [CrbController::class, 'manual'])->name('crb.manual');
    Route::get('crb/{check}/report', [CrbController::class, 'report'])->name('crb.report');
    Route::get('crb-sharing-file', [CrbController::class, 'export'])->name('crb.export')->middleware('hq');

    // Credit risk model (AI scoring)
    Route::get('risk', [RiskController::class, 'index'])->name('risk.index');
    Route::post('risk/train', [RiskController::class, 'train'])->name('risk.train')->middleware('hq');
    Route::post('risk/models/{model}/activate', [RiskController::class, 'activate'])->name('risk.activate')->middleware('hq');
    Route::get('loan-recovery', [LoanController::class, 'recovery'])->name('loans.recovery');
    Route::get('loans/{loan}/affordability', [LoanController::class, 'affordability'])->name('loans.affordability');
    Route::post('loans/{loan}/fine', [LoanController::class, 'fine'])->name('loans.fine');
    Route::post('loans/{loan}/waive', [LoanController::class, 'waive'])->name('loans.waive');
    Route::post('loans/{loan}/recovery-action', [LoanController::class, 'recoveryAction'])->name('loans.recovery-action');
    Route::get('loans/{loan}/demand-letter', [LoanController::class, 'demandLetter'])->name('loans.demand-letter');
    Route::post('loans/{loan}/write-off', [LoanController::class, 'writeOff'])->name('loans.write-off')->middleware('hq');
    Route::post('loans/{loan}/rescore', [RiskController::class, 'rescore'])->name('loans.rescore');
    Route::post('loans/{loan}/sign', [SignatureController::class, 'signLoanInOffice'])->name('loans.sign');

    // Fraud & suspicious activity
    Route::get('fraud', [FraudController::class, 'index'])->name('fraud.index');
    Route::put('fraud/{alert}', [FraudController::class, 'update'])->name('fraud.update');
    Route::post('fraud/scan', [FraudController::class, 'scan'])->name('fraud.scan');

    // BI dashboard
    Route::get('analytics', [AnalyticsController::class, 'index'])->name('analytics');

    // Cash office
    Route::group([], function () {
        Route::get('transactions', [TransactionController::class, 'index'])->name('transactions.index');
        Route::get('transactions/new', [TransactionController::class, 'create'])->name('transactions.create');
        Route::post('transactions', [TransactionController::class, 'store'])->name('transactions.store');
        Route::get('transactions/{transaction}/receipt', [TransactionController::class, 'receipt'])->name('transactions.receipt');
        Route::post('transactions/{transaction}/reverse', [TransactionController::class, 'reverse'])->name('transactions.reverse');
        Route::get('checkoff', [CheckoffController::class, 'create'])->name('checkoff.create');
        Route::post('checkoff', [CheckoffController::class, 'store'])->name('checkoff.store');
        Route::get('checkoff/template', [CheckoffController::class, 'template'])->name('checkoff.template');
        Route::get('mpesa', [CheckoffController::class, 'mpesa'])->name('mpesa.index');
        Route::post('mpesa/{mpesa}/assign', [CheckoffController::class, 'assignMpesa'])->name('mpesa.assign');

        // Self-service requests
        Route::get('service/withdrawals', [ServiceDeskController::class, 'withdrawals'])->name('service.withdrawals');
        Route::post('service/withdrawals/{w}/pay', [ServiceDeskController::class, 'payWithdrawal'])->name('service.withdrawals.pay');
        Route::post('service/withdrawals/{w}/reject', [ServiceDeskController::class, 'rejectWithdrawal'])->name('service.withdrawals.reject');

        // Savings products
        Route::get('savings/fixed-deposits', [SavingsController::class, 'fixedDeposits'])->name('savings.fd');
        Route::post('savings/fixed-deposits', [SavingsController::class, 'storeFixedDeposit'])->name('savings.fd.store');
        Route::post('savings/fixed-deposits/{fd}/close', [SavingsController::class, 'closeFixedDeposit'])->name('savings.fd.close');
        Route::get('savings/standing-orders', [SavingsController::class, 'standingOrders'])->name('savings.so');
        Route::post('savings/standing-orders', [SavingsController::class, 'storeStandingOrder'])->name('savings.so.store');
        Route::post('savings/standing-orders/{order}/toggle', [SavingsController::class, 'toggleStandingOrder'])->name('savings.so.toggle');
        Route::post('savings/interest', [SavingsController::class, 'runInterest'])->name('savings.interest');
    });
    Route::get('savings/arrears', [SavingsController::class, 'arrears'])->name('savings.arrears');

    // Service desk (all staff)
    Route::get('service/changes', [ServiceDeskController::class, 'changeRequests'])->name('service.changes');
    Route::post('service/changes/{c}', [ServiceDeskController::class, 'processChange'])->name('service.changes.process');
    Route::get('service/tickets', [ServiceDeskController::class, 'tickets'])->name('service.tickets');
    Route::post('service/tickets', [ServiceDeskController::class, 'storeTicket'])->name('service.tickets.store');
    Route::put('service/tickets/{ticket}', [ServiceDeskController::class, 'updateTicket'])->name('service.tickets.update');

    // Employers & payroll check-off
    Route::group([], function () {
        Route::get('employers', [EmployerController::class, 'index'])->name('employers.index');
        Route::post('employers', [EmployerController::class, 'store'])->name('employers.store');
        Route::get('employers/{employer}', [EmployerController::class, 'show'])->name('employers.show');
        Route::put('employers/{employer}', [EmployerController::class, 'update'])->name('employers.update');
        Route::post('employers/{employer}/generate', [EmployerController::class, 'generate'])->name('employers.generate');
        Route::post('employers/{employer}/remit', [EmployerController::class, 'remit'])->name('employers.remit');
        Route::get('checkoff/schedules/{schedule}', [EmployerController::class, 'schedule'])->name('checkoff.schedules.show');
        Route::get('checkoff/schedules/{schedule}/export', [EmployerController::class, 'export'])->name('checkoff.schedules.export');
        Route::post('checkoff/schedules/{schedule}/post', [EmployerController::class, 'post'])->name('checkoff.schedules.post');
    });

    // Loans
    Route::get('loans', [LoanController::class, 'index'])->name('loans.index');
    Route::get('loans/create', [LoanController::class, 'create'])->name('loans.create');
    Route::post('loans', [LoanController::class, 'store'])->name('loans.store');
    Route::get('loans/eligibility', [LoanController::class, 'eligibility'])->name('loans.eligibility');
    Route::get('loans/{loan}', [LoanController::class, 'show'])->name('loans.show');
    Route::get('loans/{loan}/statement', [LoanController::class, 'statement'])->name('loans.statement');
    Route::post('loans/{loan}/appraise', [LoanController::class, 'appraise'])->name('loans.appraise');
    Route::post('loans/{loan}/approve', [LoanController::class, 'approve'])->name('loans.approve');
    Route::post('loans/{loan}/reject', [LoanController::class, 'reject'])->name('loans.reject');
    Route::post('loans/{loan}/disburse', [LoanController::class, 'disburse'])->name('loans.disburse');
    Route::post('loans/{loan}/repay', [LoanController::class, 'repay'])->name('loans.repay');
    Route::post('loans/{loan}/recover', [LoanController::class, 'recover'])->name('loans.recover');
    Route::post('loans/{loan}/reschedule', [LoanController::class, 'reschedule'])->name('loans.reschedule');
    Route::post('loans/{loan}/collateral', [LoanController::class, 'addCollateral'])->name('loans.collateral.store');
    Route::post('collateral/{collateral}/release', [LoanController::class, 'releaseCollateral'])->name('loans.collateral.release');
    Route::get('collateral/{collateral}/document', [LoanController::class, 'collateralDocument'])->name('loans.collateral.document');
    Route::post('guarantors/{guarantor}/respond', [LoanController::class, 'guarantorRespond'])->name('loans.guarantor.respond');
    Route::post('guarantors/{guarantor}/replace', [LoanController::class, 'replaceGuarantor'])->name('loans.guarantor.replace');
    Route::post('guarantors/{guarantor}/release', [LoanController::class, 'releaseGuarantor'])->name('loans.guarantor.release');
    // Guarantor management
    Route::get('guarantor-management', [GuarantorController::class, 'index'])->name('guarantors.index');
    Route::get('guarantor-management/check', [GuarantorController::class, 'check'])->name('guarantors.check');
    Route::post('guarantor-management/expire', [GuarantorController::class, 'expireNow'])->name('guarantors.expire');
    Route::get('guarantor-management/{member}', [GuarantorController::class, 'show'])->name('guarantors.show');
    Route::get('guarantor-management/{member}/statement', [GuarantorController::class, 'statement'])->name('guarantors.statement');
    Route::post('guarantor-management/{member}/register', [GuarantorController::class, 'register'])->name('guarantors.register');
    Route::post('guarantor-management/{member}/status', [GuarantorController::class, 'updateStatus'])->name('guarantors.status');
    Route::post('loans/{loan}/guarantors', [GuarantorController::class, 'add'])->name('loans.guarantor.add');
    Route::post('guarantors/{guarantor}/release-decision', [GuarantorController::class, 'decideRelease'])->name('guarantors.release-decision');
    Route::resource('loan-products', LoanProductController::class)->except(['show', 'destroy']);

    // Dividends
    Route::middleware('hq')->group(function () {
        Route::get('dividends', [DividendController::class, 'index'])->name('dividends.index');
        Route::post('dividends', [DividendController::class, 'store'])->name('dividends.store');
        Route::get('dividends/{period}', [DividendController::class, 'show'])->name('dividends.show');
        Route::post('dividends/{period}/compute', [DividendController::class, 'compute'])->name('dividends.compute');
        Route::post('dividends/{period}/approve', [DividendController::class, 'approve'])->name('dividends.approve');
        Route::post('dividends/{period}/pay', [DividendController::class, 'pay'])->name('dividends.pay');
        Route::get('dividends/{period}/export', [DividendController::class, 'export'])->name('dividends.export');
    });

    // Accounting & finance
    Route::prefix('accounting')->name('accounting.')->middleware('hq')->group(function () {
        Route::get('accounts', [AccountingController::class, 'accounts'])->name('accounts');
        Route::post('accounts', [AccountingController::class, 'storeAccount'])->name('accounts.store');
        Route::put('accounts/{account}', [AccountingController::class, 'updateAccount'])->name('accounts.update');
        Route::get('accounts/{account}/ledger', [AccountingController::class, 'ledger'])->name('ledger');
        Route::get('journals', [AccountingController::class, 'journals'])->name('journals');
        Route::get('journals/create', [AccountingController::class, 'createJournal'])->name('journals.create');
        Route::post('journals', [AccountingController::class, 'storeJournal'])->name('journals.store');
        Route::get('journals/{entry}', [AccountingController::class, 'showJournal'])->name('journals.show');
        Route::post('journals/{entry}/post', [AccountingController::class, 'postJournal'])->name('journals.post');
        Route::post('journals/{entry}/reverse', [AccountingController::class, 'reverseJournal'])->name('journals.reverse');
        Route::get('trial-balance', [AccountingController::class, 'trialBalance'])->name('trial-balance');
        Route::get('profit-loss', [AccountingController::class, 'profitLoss'])->name('profit-loss');
        Route::get('balance-sheet', [AccountingController::class, 'balanceSheet'])->name('balance-sheet');
        Route::get('cash-flow', [AccountingController::class, 'cashFlow'])->name('cash-flow');
        Route::get('ratios', [AccountingController::class, 'ratios'])->name('ratios');
        Route::get('banks', [AccountingController::class, 'banks'])->name('banks');
        Route::post('banks', [AccountingController::class, 'storeBank'])->name('banks.store');
        Route::match(['get', 'post'], 'banks/{bank}/reconcile', [AccountingController::class, 'reconcile'])->name('banks.reconcile');
        Route::post('reconciliations/{rec}', [AccountingController::class, 'saveReconciliation'])->name('reconciliations.save');
        Route::post('close-year', [AccountingController::class, 'closeYear'])->name('close-year');
    });

    // Expenses & assets
    Route::group([], function () {
        Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::post('expenses/{expense}/pay', [ExpenseController::class, 'pay'])->name('expenses.pay');
        Route::get('assets', [AssetController::class, 'index'])->name('assets.index');
        Route::post('assets', [AssetController::class, 'store'])->name('assets.store');
        Route::put('assets/{asset}', [AssetController::class, 'update'])->name('assets.update');
        Route::post('assets/{asset}/dispose', [AssetController::class, 'dispose'])->name('assets.dispose');
        Route::post('assets/depreciate', [AssetController::class, 'depreciate'])->name('assets.depreciate');
    });

    // Welfare / benevolent fund
    Route::get('welfare-benefit', [WelfareController::class, 'benefitFor'])->name('welfare.benefit-for');
    Route::get('welfare/benefits', [WelfareController::class, 'benefits'])->name('welfare.benefits');
    Route::post('welfare/benefits', [WelfareController::class, 'saveBenefit'])->name('welfare.benefits.save');
    Route::delete('welfare/benefits/{benefit}', [WelfareController::class, 'deleteBenefit'])->name('welfare.benefits.delete');
    Route::post('welfare/monthly', [WelfareController::class, 'chargeMonthly'])->name('welfare.monthly');
    Route::resource('welfare', WelfareController::class)->except('destroy');
    Route::post('welfare/{welfare}/open', [WelfareController::class, 'open'])->name('welfare.open');
    Route::post('welfare/{welfare}/reject', [WelfareController::class, 'reject'])->name('welfare.reject');
    Route::post('welfare/{welfare}/contribute', [WelfareController::class, 'contribute'])->name('welfare.contribute');
    Route::post('welfare/{welfare}/retry', [WelfareController::class, 'retry'])->name('welfare.retry');
    Route::post('welfare/{welfare}/remind', [WelfareController::class, 'remind'])->name('welfare.remind');
    Route::post('welfare/{welfare}/announce', [WelfareController::class, 'announce'])->name('welfare.announce');
    Route::post('welfare/{welfare}/close', [WelfareController::class, 'close'])->name('welfare.close');
    Route::post('welfare/{welfare}/payout', [WelfareController::class, 'payout'])->name('welfare.payout');
    Route::get('welfare/{welfare}/document', [WelfareController::class, 'document'])->name('welfare.document');
    Route::post('welfare-contributions/{contribution}/waive', [WelfareController::class, 'waive'])->name('welfare.waive');

    // Automatic reminders
    Route::group([], function () {
        Route::get('reminders', [ReminderController::class, 'index'])->name('reminders.index');
        Route::post('reminders/run', [ReminderController::class, 'runNow'])->name('reminders.run');
        Route::post('reminders', [ReminderController::class, 'store'])->name('reminders.store');
        Route::put('reminders/{rule}', [ReminderController::class, 'update'])->name('reminders.update');
        Route::post('reminders/{rule}/toggle', [ReminderController::class, 'toggle'])->name('reminders.toggle');
        Route::delete('reminders/{rule}', [ReminderController::class, 'destroy'])->name('reminders.destroy');
        Route::post('loans/{loan}/remind', [ReminderController::class, 'sendLoan'])->name('loans.remind');
    });

    // Communication & governance
    Route::group([], function () {
        Route::get('messaging', [MessagingController::class, 'index'])->name('messaging.index');
        Route::post('messaging', [MessagingController::class, 'send'])->name('messaging.send');
        Route::get('governance', [GovernanceController::class, 'index'])->name('governance.index');
        Route::post('governance/committee', [GovernanceController::class, 'storeCommitteeMember'])->name('governance.committee.store');
        Route::post('governance/committee/{cm}/end', [GovernanceController::class, 'endTerm'])->name('governance.committee.end');
        Route::post('governance/meetings', [GovernanceController::class, 'storeMeeting'])->name('governance.meetings.store');
        Route::get('governance/meetings/{meeting}', [GovernanceController::class, 'showMeeting'])->name('governance.meetings.show');
        Route::put('governance/meetings/{meeting}', [GovernanceController::class, 'updateMeeting'])->name('governance.meetings.update');
        Route::post('governance/meetings/{meeting}/resolutions', [GovernanceController::class, 'storeResolution'])->name('governance.resolutions.store');
        Route::put('governance/resolutions/{resolution}', [GovernanceController::class, 'updateResolution'])->name('governance.resolutions.update');
    });

    // Reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('portfolio', [ReportController::class, 'portfolio'])->name('portfolio');
        Route::get('arrears', [ReportController::class, 'arrears'])->name('arrears');
        Route::get('balances', [ReportController::class, 'balances'])->name('balances');
        Route::get('cashbook', [ReportController::class, 'cashbook'])->name('cashbook');
        Route::get('income', [ReportController::class, 'income'])->name('income');
        Route::get('guarantors', [ReportController::class, 'guarantors'])->name('guarantors');
        Route::get('register', [ReportController::class, 'register'])->name('register');
        Route::get('profitability', [ReportController::class, 'profitability'])->name('profitability');
        Route::get('aml', [ReportController::class, 'aml'])->name('aml');
        Route::get('logins', [ReportController::class, 'logins'])->name('logins');
        Route::get('audit', [ReportController::class, 'audit'])->name('audit');
    });
});

/* ================= Administration ================= */
Route::middleware(['auth', 'role:staff', 'permission'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('users', UserController::class)->except(['show', 'destroy']);
    Route::get('roles', [\App\Http\Controllers\RoleController::class, 'index'])->name('roles.index');
    Route::post('roles', [\App\Http\Controllers\RoleController::class, 'store'])->name('roles.store');
    Route::put('roles/matrix', [\App\Http\Controllers\RoleController::class, 'matrix'])->name('roles.matrix');
    Route::get('roles/{role}', [\App\Http\Controllers\RoleController::class, 'edit'])->name('roles.edit');
    Route::put('roles/{role}', [\App\Http\Controllers\RoleController::class, 'update'])->name('roles.update');
    Route::delete('roles/{role}', [\App\Http\Controllers\RoleController::class, 'destroy'])->name('roles.destroy');
    Route::get('settings', [SettingController::class, 'edit'])->name('settings');
    Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
    Route::post('account-types', [SettingController::class, 'storeAccountType'])->name('account-types.store');
    Route::put('account-types/{accountType}', [SettingController::class, 'updateAccountType'])->name('account-types.update');
    Route::post('categories', [SettingController::class, 'storeCategory'])->name('categories.store');

    // Branches
    Route::get('branches', [BranchController::class, 'index'])->name('branches');
    Route::post('branches', [BranchController::class, 'store'])->name('branches.store');
    Route::put('branches/{branch}', [BranchController::class, 'update'])->name('branches.update');

    // Partner API & webhooks
    Route::get('api', [ApiClientController::class, 'index'])->name('api');
    Route::get('api/docs', [ApiClientController::class, 'docs'])->name('api.docs');
    Route::post('api', [ApiClientController::class, 'store'])->name('api.store');
    Route::put('api/{client}', [ApiClientController::class, 'update'])->name('api.update');
    Route::post('api/{client}/regenerate', [ApiClientController::class, 'regenerate'])->name('api.regenerate');
    Route::post('api/{client}/test-webhook', [ApiClientController::class, 'testWebhook'])->name('api.test-webhook');
    Route::post('webhooks/{delivery}/redeliver', [ApiClientController::class, 'redeliver'])->name('api.redeliver');

    // SMS, email, M-Pesa & WhatsApp integrations
    Route::get('integrations', [IntegrationController::class, 'edit'])->name('integrations');
    Route::put('integrations/{group}', [IntegrationController::class, 'update'])->name('integrations.update');
    Route::post('integrations/test/sms', [IntegrationController::class, 'testSms'])->name('integrations.test.sms')->middleware('throttle:10,1');
    Route::post('integrations/test/email', [IntegrationController::class, 'testEmail'])->name('integrations.test.email')->middleware('throttle:10,1');
    Route::post('integrations/test/mpesa', [IntegrationController::class, 'testMpesa'])->name('integrations.test.mpesa');
    Route::post('integrations/test/stk', [IntegrationController::class, 'testStk'])->name('integrations.test.stk')->middleware('throttle:5,1');
    Route::post('integrations/test/whatsapp', [IntegrationController::class, 'testWhatsapp'])->name('integrations.test.whatsapp')->middleware('throttle:10,1');
    Route::post('integrations/mpesa/register', [IntegrationController::class, 'registerC2b'])->name('integrations.mpesa.register');
});
