# SACCO Management System (Laravel 12/13)

A SACCO core-banking system built for Kenyan SACCOs. It covers membership, BOSA/FOSA savings, loans with guarantors and collateral, payroll check-off, M-Pesa, double-entry accounting, dividends, a member portal, governance and compliance reporting.

---

## 1. Installation (extract into an existing Laravel project)

1. Create the project (cmd):
   ```bat
   cd C:\xampp\htdocs
   composer create-project laravel/laravel sacco
   cd sacco
   composer require barryvdh/laravel-dompdf
   ```
2. Extract `sacco-code.zip` into the project root and **replace** files when asked.
3. In `.env`, set the following, and create the `sacco` database in phpMyAdmin:
   ```env
   DB_CONNECTION=mysql
   DB_DATABASE=sacco
   DB_USERNAME=root
   DB_PASSWORD=
   SESSION_DRIVER=database
   APP_TIMEZONE=Africa/Nairobi
   ```
4. Run:
   ```bat
   php artisan optimize:clear
   php artisan migrate --seed
   php artisan storage:link
   php artisan serve
   ```
   Optional demo data: `php artisan db:seed --class=DemoSeeder`. To see the AI model work: `php artisan db:seed --class=DemoRiskHistorySeeder` (both **test databases only**).
5. Log in with `admin@sacco.local` / `Admin@123` and change the password. Then fill in **Admin → Integrations**.
6. Scheduler: add a Windows Task Scheduler task (or cron) that runs `php artisan schedule:run` every minute in the project folder.

For servers, Docker and multi-SACCO hosting, see `deploy/DEPLOY.md`.

---

## 2. Coverage against the requirement list

✅ done · 🟡 partly done or done differently · ❌ not included (see section 8)

| # | Area | Status | Notes |
|---|---|---|---|
| 1 | **Member management** | ✅ | Registration, auto member no., profile, next of kin/nominees with % split, **document uploads** (ID, passport, photo, payslip…) with verification, **approval workflow** (pending → approved, ID required), categories, employer grouping, status (active/dormant/suspended/exited), **exit & clearance** (loan offset, refund, share transfer), portal |
| 2 | **Savings & deposits** | ✅ | Configurable savings products (add e.g. Holiday or Junior accounts in Settings), mandatory monthly contributions with **arrears tracking**, voluntary savings, **fixed deposits** (term, rate, WHT, early break, auto-rollover), **standing orders**, monthly **savings interest** posting, minimum balance, withdrawal fees, statements (screen + PDF) |
| 3 | **Loans** | ✅ | Products (flat/reducing, multiplier, fees, insurance, penalties, guarantors, minimum tenure), online applications, live eligibility, **rule-based credit score (0–100, A–E)**, **multi-level approval** by amount threshold, maker-checker, **collateral register** with documents, disbursement (FOSA/bank/M-Pesa/cheque), schedules, **top-ups**, **rescheduling/restructuring** (new period or rate), automatic penalties, arrears and aging, guarantor recovery, loan statements |
| 4 | **Guarantors** | ✅ | Consent via portal/app/SMS, eligibility checks, maximum guarantee = free deposits, **exposure falls automatically as principal is repaid**, replacement and release, guarantor liability report |
| 5 | **Accounting & finance** | ✅/🟡 | **Automated double-entry**: every member transaction and repayment posts a balanced journal in real time. Chart of accounts, manual journals (maker-checker), GL ledgers, bank accounts, **bank reconciliation**, cash book, **accounts payable** (bills), trial balance, income statement, balance sheet, **cash-flow statement**, **year-end close and period lock**, fixed-asset depreciation journals. 🟡 Accounts receivable is limited to employer check-off and the receivables GL (no invoicing). ❌ Budgets |
| 6 | **Payments** | ✅/🟡 | M-Pesa **C2B paybill** and **STK Push** (Daraja), mobile deposits and repayments, payroll deductions, bulk CSV posting, reconciliation (unmatched M-Pesa queue, check-off variance, bank rec), SMS confirmations, reference tracking, reversals. 🟡 Bank payments are bulk CSV only, with no bank API. Loan payouts via M-Pesa B2C are recorded but not pushed automatically |
| 7 | **Payroll & employers** | ✅ | Employer register, member–employer link, **check-off file generation** (contributions + installments due), export to employer, post returned deductions, **expected-vs-actual reconciliation**, remittance tracking, **outstanding remittance report** |
| 8 | **Dividends & interest** | ✅ | Dividend on shares, interest on average monthly deposits, WHT, compute → approve → post (to FOSA or capitalised), GL appropriation, CSV, member dividend statement in the portal |
| 9 | **Member portal** | ✅ | Secure login (optional OTP), balances, loans, apply online, track status, contribution history, **PDF statement**, guarantor obligations, **withdrawal requests**, **profile-update requests** (staff verify), dividends, **complaints & inquiries** |
| 10 | **Mobile app** | ❌ | Not included |
| 11 | **Notifications** | ✅ | SMS (Advanta), email, **WhatsApp (Meta Cloud API)**, all logged. Automatic messages for deposits, approvals, disbursements, repayment reminders, weekly arrears and contribution-arrears notices, guarantor requests. **Bulk messaging** with placeholders and audiences (all, arrears, employer, category, board) for AGM notices and announcements |
| 12 | **Reports** | ✅/🟡 | Member register, savings balances, contribution arrears, portfolio, arrears/aging/PAR with SASRA provisioning, guarantor exposure, financial statements, cash flow, I&E, dividends, **member profitability**, AML alerts, login history, audit trail, dashboard. CSV (opens in Excel) and print/PDF. 🟡 No drag-and-drop custom report builder |
| 13 | **Governance** | ✅/🟡 | Board and committee register with terms, meetings (board/committee/AGM/SGM) with SMS/email notices, agenda, **minutes**, **resolutions**, complaints, maker-checker approvals, staff users. 🟡 Elections: use your Staff e-Voting system. ❌ Policy document library |
| 14 | **Security** | ✅/🟡 | Role-based access (6 roles), **OTP two-factor** for staff and/or members, password policy and **expiry**, audit trail, login history (including failures), DB sessions, throttling, **staff IP allowlist**, **daily DB backups** with retention, maker-checker. 🟡 Permissions are per role, not per user. Encryption at rest and off-site DR copies are server/hosting tasks. ❌ Device binding |
| 15 | **Compliance** | 🟡 | KYC/CDD fields (occupation, source of funds, risk rating, PEP), **AML flag for large cash transactions**, Data Protection Act consent capture, WHT on dividends and FD interest, full audit trail, **prudential ratios** (core capital, liquidity, PAR) against SASRA thresholds. 🟡 These are indicative figures, not the official SASRA return templates. Interest is recognised on a cash basis; IFRS 9 ECL provisioning is not modelled. ❌ Returns-submission tracking |
| 16 | **Fixed assets** | ✅/🟡 | Asset register, categories, straight-line or reducing-balance **depreciation (auto monthly journal)**, disposal with gain/loss, transfer of location/custodian, maintenance dates. ❌ Inventory/stock |
| 17 | **Workflow & automation** | ✅ | Amount-based approval levels, automatic eligibility, schedules, penalties, savings interest, standing orders, FD maturity, arrears notices, accounting entries, year-end close, backups. ❌ Emailed scheduled reports |
| 18 | **Advanced** | ✅/🟡 | See section 7: AI credit scoring & loan risk prediction, fraud monitoring, CRB (Metropol), partner API & webhooks, digital onboarding, e-signatures, ID verification (IPRS via Smile ID + face review), BI dashboard, multi-branch, multi-SACCO, cloud deployment kit |

---

## 3. Roles

| Role | Can do |
|---|---|
| **Teller** | Deposits, withdrawals, repayments, withdrawal requests, M-Pesa queue, bulk posting, employers and check-off, FDs, standing orders |
| **Loan officer** | Capture and appraise loans, collateral, guarantor forms and replacement, top-ups |
| **Manager** | Approve loans (levels 1–2), approve members, exits, reschedule, release guarantors/collateral, dividends approval, bulk messaging, governance, AML and login reports, year-end close |
| **Accountant** | Disburse loans, reversals, journals, bank reconciliation, expenses and payables, assets, dividends, savings interest, remittances, financial statements |
| **Admin** (Board Chair / ICT) | Everything, plus level-3 loan approvals, staff users and settings |
| **Member** | Portal only |

---

## 4. How the money flows (accounting)

Every financial event creates a balanced journal automatically:

| Event | Debit | Credit |
|---|---|---|
| Deposit (cash/M-Pesa/bank) | Cash / M-Pesa / Bank | Member deposits, savings or share capital |
| Payroll check-off deduction | Check-off receivable | Member account / loan |
| Employer remittance received | Bank | Check-off receivable |
| Withdrawal | Member savings | Cash / Bank / M-Pesa |
| Loan disbursement | Loans to members (full principal) | Savings or Bank (net), Processing fee income, Insurance payable |
| Loan repayment | Cash / M-Pesa / Bank / Check-off | Loans (principal), Interest income, Penalty income |
| Expense / bill | Expense account | Cash/Bank, or Accounts payable |
| Dividends | Dividend appropriation (gross) | WHT payable, member accounts (net) |
| Savings / FD interest | Interest expense | Member savings |
| Depreciation | Depreciation expense | Accumulated depreciation |

Internal moves (transfers, standing orders, guarantor recovery, top-up payoff, exit offsets) pass through **Internal Clearing**, which should always net to zero. If it doesn't, something needs investigating.

If a journal can't post (for example, the books are closed for that date), the member transaction is **rolled back**. The member ledger and the GL can never drift apart.

---

## 5. Configuration

### SMS, email, M-Pesa and WhatsApp: Admin → Integrations
Log in as admin and open **Integrations** in the sidebar. Each tab has the credential fields, a **Save** button and a **test** button:

| Tab | What you enter | Test |
|---|---|---|
| **SMS (Advanta)** | Send-SMS switch, partner ID, sender ID/shortcode, API key | Sends a test SMS to a phone |
| **Email (SMTP)** | Host, port, TLS/SSL, username, password (Gmail: app password), from address/name | Sends a test email |
| **M-Pesa (Daraja)** | Sandbox/production, paybill, STK shortcode, consumer key/secret, passkey, public HTTPS URL | Test connection · **Register C2B URLs** · Send a KES 1 STK prompt |
| **WhatsApp** (optional) | Phone number ID, access token | Sends a test message |

- Values saved there **override `.env`**. Blank fields fall back to `.env`.
- Keys and passwords are stored **encrypted** with APP_KEY and never displayed again. Keep a backup of `.env`.
- The **M-Pesa callback URLs** are `https://<public-url>/api/payments/c2b/confirmation`, `/c2b/validation` and `/stk/callback`, each with a security token. Safaricom rejects URLs containing "mpesa", which is why they live under `/payments`. The **Register C2B URLs** button sends them to Safaricom for you.
- **Testing on XAMPP:** run `ngrok http 80` and paste the `https://…ngrok-free.app/sacco/public` address into *Public HTTPS base URL*. On the live server, use your domain.
- Members pay to your paybill with account `MBR00001` (deposits), `MBR00001-SHR` or `MBR00001-SAV`, or a loan number. Payments with an unknown account appear under **M-Pesa Payments** for manual posting.

### `.env` alternative
You can also set the same values in `.env`: `SMS_ENABLED`, `ADVANTA_*`, `MAIL_*`, `MPESA_ENV`, `MPESA_PAYBILL`, `MPESA_STK_SHORTCODE`, `MPESA_CONSUMER_KEY`, `MPESA_CONSUMER_SECRET`, `MPESA_PASSKEY`, `MPESA_CALLBACK_TOKEN`, `PUBLIC_URL`, `WHATSAPP_*`, plus `MYSQLDUMP_PATH="C:\xampp\mysql\bin\mysqldump.exe"` for backups.

**In-app Settings (Admin → Settings):**
- Loan approval thresholds (2nd approver, Board Chair)
- Top-up rule
- FD minimum, FD WHT and FD break rate
- AML cash threshold
- Staff/member OTP
- Staff IP allowlist
- Password expiry
- Books lock date
- Savings product interest rates and GL mapping

**Scheduled jobs** (`php artisan schedule:run` every minute):

| Job | When |
|---|---|
| Standing orders | 00:30 |
| FD maturity | 00:45 |
| Penalties | 01:00 |
| Backup | 02:00 |
| Repayment reminders | 08:00 |
| Arrears notices | Mondays |
| Savings interest, depreciation, dormancy | Monthly |

---

## 6. Go-live checklist
1. Change the admin password and create named staff users. Turn on `staff_2fa` and set the IP allowlist for the office network.
2. Settings: SACCO details, member prefix, fees, approval thresholds.
3. Review the chart of accounts (rename or add banks), loan products and savings products.
4. Register employers. Members can be imported by registering them, or ask for a CSV importer.
5. **Opening balances:**
   - Use *Bulk Posting (CSV)* with source **"Go-live opening balances"** (`member_no,item,amount`). This credits SHR/DEP/SAV against *Opening Balances (Go-live)* equity.
   - For running loans, capture each loan for its **outstanding principal and remaining months** and disburse it with channel **"Go-live balance migration"**.
   - Post opening GL balances for cash, bank, assets and payables as one manual journal against Opening Balances. Opening Balances should end at zero once everything is in.
6. In **Admin → Integrations**, enter the SMS, email and M-Pesa keys, use each test button, and click **Register C2B URLs**. Test with small amounts.
7. Run the trial balance and balance sheet, and check that Internal Clearing is zero.
8. Set `APP_ENV=production` and `APP_DEBUG=false`, serve over HTTPS, and copy `storage/app/backups` off-site every day.

---

## 7. Advanced features

| Feature | Where | How it works |
|---|---|---|
| **AI credit scoring & loan risk prediction** | Credit Risk (AI); AI card on each loan | A **logistic-regression model trained on your own loan history**. Features are captured at application time (tenure, saving consistency, deposits, loan ÷ deposits, existing debt, installment ÷ contribution, past arrears, loans cleared, period, age, guarantor cover, top-up). It predicts the **probability of default (PD)**, band A–E and a recommendation, and shows the factors that drove each prediction. Running loans are re-scored with current arrears, and expected loss = PD × balance × LGD. Retrains monthly; needs ≥ 50 finished loans incl. ≥ 5 defaults, otherwise the rule-based score is used. Try it with `php artisan db:seed --class=DemoRiskHistorySeeder` (**demo only**). |
| **Fraud & suspicious-transaction monitoring** | Fraud Monitoring | Real-time rules on every posting: large cash, structuring (split deposits under the threshold), velocity, rapid in-and-out, dormant-account activity, amount anomaly (z-score against the member's own history), after-hours cash, back-dating, staff posting to their own account. Nightly pattern rules: shared phone/email across members, frequent reversals by one user, guarantee concentration. Review queue with investigate / clear / **report to FRC (goAML STR ref)**. Thresholds in Settings (`fraud_*`). |
| **Credit reference bureau** | CRB card on loans; CRB Checks; Integrations → CRB | **Metropol REST API** (signed SHA-256 requests) for score, delinquency, identity and report, with member consent enforced. Manual entry with PDF upload for TransUnion, Creditinfo or Metropol's web portal. Optional rule: no approval without a CRB check in 30 days (`require_crb_check`). Monthly **credit-information-sharing CSV** for submission to the bureaus. |
| **Open banking / third-party API** | Admin → API & Webhooks (docs built in) | Partner API at `/api/v1` with per-system **keys (hashed), scopes, IP allowlists, rate limits and request logs**. Endpoints: validate member, balances, loan status, **post payments (idempotent)**, submit member applications, export transactions. **Signed webhooks** (HMAC-SHA256) for `transaction.posted`, `loan.disbursed`, `loan.repaid`, `member.created`, `member.approved`, with automatic retries. |
| **Digital member onboarding** | Public page `/join` (link on the login page) | Self-service application: personal details, **phone OTP**, employer and contribution, nominee, ID front/back upload, **live selfie via camera**, by-laws, data-protection and CRB consents, portal password, **e-signature**. Arrives as *pending* for staff KYC. Approval activates the login. Auto IPRS check if Smile ID is configured. |
| **Electronic signatures** | Online application, portal loan agreement, guarantor acceptance, counter capture on the loan screen | Drawn signature plus signer, time, IP/device and a **SHA-256 fingerprint of the exact terms**. The loan agreement (print/PDF) embeds every signature with a **verification code**, and anyone can check it at `/verify/{code}`, which reports *genuine*, *altered* or *not found*. |
| **Biometric identity verification** | Member → Verify identity | **IPRS registry match via Smile ID** (ID number, names, DOB). The **live selfie vs ID photo** is reviewed side by side by staff (match / no match), and staff can capture a live photo at the counter by webcam. Identity status: unverified, verified or failed. |
| **Business intelligence** | Analytics (BI) | KPIs (growth, savings, loan book, PAR30, NPL, collection efficiency, loan yield, cost-to-income, loans/savings), monthly trends, aging, product mix, demographics, top employers, **branch comparison**, CSV export, branch and period filters. |
| **Multi-branch** | Admin → Branches; branch on each staff user | Members, loans, transactions, journals and expenses are tagged with a branch automatically. Branch staff see their own branch's lists. Head-office staff (no branch) see all branches and run GL, dividends, integrations and the AI model. Members can transact at any branch, and HQ can move a member between branches. |
| **Multi-SACCO** | `MULTI_TENANT=true` + `php artisan sacco:tenant-create` | One installation serves many SACCOs, **one database each, chosen by domain**. Each SACCO has its own users, settings, integration keys and files, and the scheduler runs per SACCO. See `deploy/DEPLOY.md`. |
| **Cloud** | `deploy/` folder | Guides for DirectAdmin/cPanel, Ubuntu VPS (Nginx, PHP-FPM, certbot, cron) and **Docker Compose** (app, scheduler, nginx, MySQL). Security headers, trusted proxies, off-site backup and restore guidance. |

New scheduled jobs: risk refresh (daily), fraud scan (daily), webhook retries (every 5 min), model retraining (monthly).

**What needs a contract or confirmation before going live**
- **Metropol:** keys come with your CRB subscription. Confirm the endpoint paths and timestamp format against the API document they send you, and test on their testbed first.
- **Smile ID:** you need a partner account. Confirm the request fields for your product (Enhanced KYC). Automated face-match ("Biometric KYC") requires their capture SDK. Until then, face matching is a staff review.
- **The AI model is only as good as your history.** Watch the validation AUC on the Credit Risk page. Below 0.7, treat the scores as indicative. It supports, and does not replace, the credit committee.

## 7b. Member deposits and loan management

| Feature | Where |
|---|---|
| Members deposit / pay online | Portal → Savings → **Deposit / pay (M-Pesa)**: pick deposits, shares, savings or a loan; an M-Pesa PIN prompt goes to the phone and the page confirms when it lands. Paybill instructions show the right account number. Needs the STK shortcode + passkey in Integrations. |
| Loan product configuration | Loans → Loan products: rate, method, period and amount limits, × deposits, guarantors, fees, penalty, grace period, max running loans, top-ups allowed, collateral required, ⅔ deduction rule |
| Online applications | Portal → Apply for a loan (guarantor confirmation by member no.) |
| Eligibility | Live check on the application form (deposits multiple, membership months, running loans, product limits) |
| Appraisal & credit scoring | Loan page → Appraise: payslip affordability test (live), rule-based score, AI risk prediction, CRB check |
| Approval workflow, multiple levels | Settings: level-2 and level-3 thresholds; approval trail on the loan page |
| Guarantors | Loan page: accept/decline, replace, release, exposure; portal guarantor obligations |
| Collateral | Loan page → Collateral & security (documents, release); "collateral required" per product |
| Disbursement | Loan page → Disburse (FOSA, bank, M-Pesa B2C, cheque); first due date respects the grace period |
| Interest methods | Reducing balance (equal installments), reducing balance with equal principal, flat rate |
| Repayment schedule | Loan page, loan calculator, portal |
| Restructure / reschedule | Loan page → Reschedule (new period/rate on the remaining principal) |
| Top-ups & refinancing | Loan page → Top-up / refinance (old balance cleared from the new loan) |
| Penalties & fines | Automatic monthly penalties; Loan page → Charge a fine; Waive penalty/interest (manager) |
| Arrears tracking | Loans → **Loan recovery** queue (days, arrears, classification, stage); arrears report |
| Recovery management | Recovery log (calls, visits, promises to pay, guarantor notices by SMS), printable **demand letter**, recover from guarantors, **write-off** (HQ manager, after `write_off_min_days`) with later recoveries posted to Bad Debts Recovered |
| Statements & repayment history | Loan page → Loan statement (staff); portal loan page → Loan statement & repayment history |

New settings: `write_off_min_days` (180), `demand_notice_days` (14). The command `sacco:recovery-review` runs daily at 07:30 and marks promises to pay as kept or broken.

## 7c. Welfare fund, automatic reminders and colour theme

**Welfare / benevolent fund** (staff menu → Welfare; member portal → Welfare)
- Create an event for a member: bereavement (and whose: member, spouse, child, parent...), wedding, new baby, sickness, graduation, retirement, disaster or other.
- How members contribute:
  - **Fixed levy**: the same amount from every active member. It's deducted from savings (or deposits, if your by-laws allow). Members without enough balance get it listed as *unpaid*, receive reminders, and can pay by M-Pesa.
  - **Voluntary**: members give what they wish, by M-Pesa (STK prompt or Paybill account `MEMBERNO-W{id}`), from their savings in the portal, or at the counter.
  - **Both**.
- **Benefit schedule** (Welfare → Benefits): what the fund itself pays per event type and relationship. It pre-fills each event.
- Opening an event SMSes all members. Staff can retry deductions after salaries land, remind unpaid members, waive hardship cases, close the event, and **pay out** (to savings, M-Pesa, bank, cheque or cash). The payout is checked against the fund balance.
- Members can **report an event / request support** from the portal; a manager reviews and approves it.
- **Optional monthly welfare contribution** (Settings: amount, day, SAV/DEP) to build the reserve; Paybill account `MEMBERNO-WLF`.
- Accounting: all welfare money goes through GL **2400 Welfare / Benevolent Fund** (liability).

**Automatic reminders** (Loans → Automatic reminders)
- Editable rules run daily at 08:00: 3 days before an installment, on the due date, 1/7/30/60 days overdue (30 and 60 also SMS the guarantors), loan maturing, promise-to-pay due, monthly contribution, contribution arrears and unpaid welfare levies.
- Each reminder goes once. There is a "Preview today's" view, a "Send due reminders now" button, a full log, and a **Send SMS reminder now** button on every loan.
- Command: `php artisan sacco:send-reminders --dry-run`.

**Colours**: Administration → Settings → Colour theme. Navy is the default; you can also choose purple, maroon, teal, charcoal & gold, orange, or the original green.

## 7d. Guarantor management (Loans → Guarantor management)

| Feature | Where / how |
|---|---|
| Guarantor registration | Members click **Register as a guarantor** in Portal → Guarantees, or staff register them from the guarantor profile. Turn on `guarantor_registration_required` to accept only registered guarantors. |
| Consent & approval | Guarantor accepts with an e-signature or declines with a reason in the portal; staff can record a signed paper form. The consent channel, time and IP are kept. Requests lapse after `guarantor_request_days` (2-day reminder before), and the borrower is told to add someone else. |
| Eligibility checks | Active member; not suspended or blocked; registered (if required); membership ≥ `guarantor_min_months`; own loans not over `guarantor_max_arrears_days` late; no written-off loan; under `guarantor_max_loans`; enough free capacity; mutual-guarantee rule. Checked on request, on acceptance and on replacement, with a live check on the loan page and in the portal. |
| Maximum guarantee limits | Limit = loanable deposits × `guarantor_deposit_multiple`, capped by `guarantor_max_amount`, or an individual limit per member (guarantor profile). |
| Tracking guaranteed loans | Guarantor management → Guaranteed loans (search, status, loans in arrears), Awaiting consent, Release requests, Guarantor register; guarantor profile per member; member's portal page |
| Liability reports | Reports → Guarantor liability (exposure by borrower arrears bucket, at-risk amounts, limits, recoveries, CSV) and a printable **guarantor liability statement** per member |
| Automatic exposure reduction | Exposure = guaranteed × principal balance ÷ amount disbursed, shown as "% reduced". All guarantors are released and SMSed when the loan is cleared. |
| Replacement & release | Staff or the borrower (in the portal) replace a guarantor; the old guarantor stays liable until the new one accepts. Guarantors can **ask to be released** in the portal; a manager approves only if the loan stays covered. Rejected loans withdraw their requests. Recoveries from guarantors are recorded per guarantor and SMSed. |

Daily job: `sacco:guarantor-requests` (07:45).

## 7e. Roles & permissions (Administration → Roles & permissions)

- **74 permissions** in 9 groups (Members, Front office, Loans, Risk & compliance, Welfare, Dividends & accounting, Communication & governance, Reports, Administration). They're defined in `config/permissions.php`.
- **Every staff page and action is checked against a permission.** `config/permissions.php → routes` maps each route to its permission, and buttons and menu items hide automatically when the user lacks it (`can_route('loans.approve')`). Permissions also work with `@can('loans.approve')`, `$user->can()` and `->middleware('permission:loans.approve')`.
- **Built-in roles:** Administrator (always everything), Manager, Loan Officer, Accountant, Teller and Member. On install they get exactly the access they had before (verified route by route), and you can change it from then on.
- **Custom roles:** create one (e.g. *Welfare Officer*, *Credit Committee Member*, *Auditor (read-only)*), start from a copy of an existing role, and tick its permissions on the role page or in the full **permission matrix**.
- **Users:** one main role plus optional **additional roles**. Their permissions combine. The user page shows exactly what that person can do.
- **Special permissions:** *Final-level (board) loan approval* (level-3 approvals) and *Go-live opening balances*.
- **Safeguards:**
  - Only administrators can edit administrators.
  - Someone who isn't an administrator can only assign roles, and grant or remove permissions, that they hold themselves.
  - You can't change your own role or disable yourself.
  - Built-in roles can't be deleted, and a role can't be deleted while it is someone's main role.
  - Every change is written to the audit trail.
- After adding permissions to the config: `php artisan sacco:sync-permissions`. To reset the built-in roles to their defaults: `php artisan sacco:sync-permissions --reset`.

## 8. Not included (separate projects or integrations)
- **Mobile app** and its member API.
- **TransUnion / Creditinfo APIs** (manual entry is supported); automated face matching (needs Smile ID's SDK); advanced e-signatures via a licensed certification provider.
- **Official SASRA return templates, IFRS 9 ECL, budgets, inventory, HR/payroll.**
- **Bank API integrations and M-Pesa B2C payouts.**
