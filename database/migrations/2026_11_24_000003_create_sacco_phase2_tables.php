<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* ---------- Accounting (double entry) ---------- */
        Schema::create('gl_accounts', function (Blueprint $t) {
            $t->id();
            $t->string('code', 20)->unique();
            $t->string('name');
            $t->enum('type', ['asset', 'liability', 'equity', 'income', 'expense']);
            $t->foreignId('parent_id')->nullable()->constrained('gl_accounts')->nullOnDelete();
            $t->string('system_key', 40)->nullable()->unique(); // used by auto-posting
            $t->boolean('is_cash')->default(false);             // cash/bank/mobile money (cash-flow statement)
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('journal_entries', function (Blueprint $t) {
            $t->id();
            $t->string('entry_no', 20)->unique();
            $t->date('entry_date');
            $t->string('description');
            $t->string('source_type')->nullable();
            $t->unsignedBigInteger('source_id')->nullable();
            $t->enum('status', ['draft', 'posted', 'reversed'])->default('posted');
            $t->boolean('is_closing')->default(false);
            $t->foreignId('reversal_of')->nullable()->constrained('journal_entries')->nullOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('posted_at')->nullable();
            $t->timestamps();
            $t->index(['source_type', 'source_id']);
            $t->index(['entry_date', 'status']);
        });

        Schema::create('journal_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $t->foreignId('gl_account_id')->constrained();
            $t->decimal('debit', 16, 2)->default(0);
            $t->decimal('credit', 16, 2)->default(0);
            $t->string('memo')->nullable();
            $t->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('reconciled_at')->nullable();
            $t->foreignId('reconciliation_id')->nullable();
            $t->timestamps();
            $t->index('gl_account_id');
        });

        Schema::create('bank_accounts', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('bank')->nullable();
            $t->string('account_number')->nullable();
            $t->foreignId('gl_account_id')->constrained();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('bank_reconciliations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('bank_account_id')->constrained();
            $t->date('statement_date');
            $t->decimal('statement_balance', 16, 2);
            $t->decimal('cleared_balance', 16, 2)->default(0);
            $t->decimal('difference', 16, 2)->default(0);
            $t->enum('status', ['open', 'completed'])->default('open');
            $t->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::table('account_types', function (Blueprint $t) {
            $t->foreignId('gl_account_id')->nullable()->constrained('gl_accounts')->nullOnDelete();
            $t->decimal('interest_rate', 6, 2)->default(0); // % p.a. posted monthly (savings interest)
            $t->decimal('monthly_minimum', 14, 2)->default(0); // mandatory monthly contribution for arrears tracking
        });

        Schema::table('expenses', function (Blueprint $t) {
            $t->foreignId('gl_account_id')->nullable()->constrained('gl_accounts')->nullOnDelete();
            $t->foreignId('paid_from_gl_id')->nullable()->constrained('gl_accounts')->nullOnDelete();
            $t->boolean('is_paid')->default(true); // false = accounts payable
        });

        /* ---------- Members: categories, employers, documents, KYC ---------- */
        Schema::create('member_categories', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('description')->nullable();
            $t->timestamps();
        });

        Schema::create('employers', function (Blueprint $t) {
            $t->id();
            $t->string('code', 20)->unique();
            $t->string('name');
            $t->string('contact_person')->nullable();
            $t->string('phone', 20)->nullable();
            $t->string('email')->nullable();
            $t->string('address')->nullable();
            $t->unsignedTinyInteger('payroll_day')->default(25);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::table('members', function (Blueprint $t) {
            $t->foreignId('member_category_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('employer_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->string('exit_reason')->nullable();
            $t->string('occupation')->nullable();
            $t->string('source_of_funds')->nullable();       // AML / CDD
            $t->enum('risk_rating', ['low', 'medium', 'high'])->default('low');
            $t->boolean('is_pep')->default(false);           // politically exposed person
            $t->timestamp('data_consent_at')->nullable();    // Data Protection Act
        });

        Schema::create('member_documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('member_id')->constrained()->cascadeOnDelete();
            $t->string('type', 40); // national_id, passport, photo, kra_pin, payslip, signature, other
            $t->string('title');
            $t->string('path');
            $t->boolean('verified')->default(false);
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('member_change_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('member_id')->constrained()->cascadeOnDelete();
            $t->json('changes');
            $t->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $t->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        /* ---------- Payroll check-off ---------- */
        Schema::create('checkoff_schedules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employer_id')->constrained();
            $t->string('period', 7); // YYYY-MM
            $t->decimal('expected_total', 16, 2)->default(0);
            $t->decimal('received_total', 16, 2)->default(0);
            $t->enum('status', ['generated', 'sent', 'posted', 'reconciled'])->default('generated');
            $t->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['employer_id', 'period']);
        });

        Schema::create('checkoff_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('checkoff_schedule_id')->constrained()->cascadeOnDelete();
            $t->foreignId('member_id')->constrained();
            $t->string('item', 30); // account code or loan no
            $t->decimal('expected', 14, 2)->default(0);
            $t->decimal('received', 14, 2)->default(0);
            $t->boolean('posted')->default(false);
            $t->string('error')->nullable();
            $t->timestamps();
        });

        Schema::create('employer_remittances', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employer_id')->constrained();
            $t->foreignId('checkoff_schedule_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $t->decimal('amount', 16, 2);
            $t->date('received_on');
            $t->string('reference', 60)->nullable();
            $t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        /* ---------- Savings products ---------- */
        Schema::create('fixed_deposits', function (Blueprint $t) {
            $t->id();
            $t->string('fd_no', 20)->unique();
            $t->foreignId('member_id')->constrained();
            $t->decimal('amount', 16, 2);
            $t->decimal('rate', 6, 2);
            $t->unsignedSmallInteger('term_months');
            $t->date('start_date');
            $t->date('maturity_date');
            $t->decimal('interest_amount', 16, 2);
            $t->decimal('wht', 16, 2)->default(0);
            $t->enum('status', ['active', 'matured', 'broken'])->default('active');
            $t->boolean('auto_rollover')->default(false);
            $t->date('closed_on')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('standing_orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('member_id')->constrained();
            $t->foreignId('from_account_id')->constrained('member_accounts');
            $t->enum('target_type', ['account', 'loan']);
            $t->foreignId('target_account_id')->nullable()->constrained('member_accounts');
            $t->foreignId('loan_id')->nullable()->constrained();
            $t->decimal('amount', 14, 2);
            $t->unsignedTinyInteger('day_of_month');
            $t->boolean('is_active')->default(true);
            $t->date('last_run_on')->nullable();
            $t->string('last_result')->nullable();
            $t->timestamps();
        });

        /* ---------- Loan extensions ---------- */
        Schema::table('loans', function (Blueprint $t) {
            $t->unsignedTinyInteger('approvals_required')->default(1);
            $t->unsignedTinyInteger('credit_score')->nullable();
            $t->foreignId('top_up_of')->nullable()->constrained('loans')->nullOnDelete();
            $t->decimal('top_up_payoff', 16, 2)->default(0);
            $t->timestamp('rescheduled_at')->nullable();
            $t->unsignedTinyInteger('reschedule_count')->default(0);
        });

        Schema::create('loan_approvals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('level');
            $t->foreignId('user_id')->constrained();
            $t->decimal('amount', 16, 2);
            $t->string('remarks')->nullable();
            $t->timestamps();
        });

        Schema::create('loan_collaterals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $t->string('type', 40); // title_deed, logbook, shares, salary, equipment, other
            $t->string('description');
            $t->decimal('value', 16, 2)->default(0);
            $t->string('reference')->nullable();
            $t->string('document')->nullable();
            $t->enum('status', ['held', 'released'])->default('held');
            $t->timestamps();
        });

        /* ---------- Self-service ---------- */
        Schema::create('withdrawal_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('member_id')->constrained();
            $t->string('account_code', 20);
            $t->decimal('amount', 14, 2);
            $t->enum('channel', ['mpesa', 'bank', 'cash']);
            $t->string('destination')->nullable();
            $t->string('reason')->nullable();
            $t->enum('status', ['pending', 'paid', 'rejected'])->default('pending');
            $t->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('transaction_id')->nullable();
            $t->string('remarks')->nullable();
            $t->timestamps();
        });

        Schema::create('tickets', function (Blueprint $t) {
            $t->id();
            $t->string('ticket_no', 20)->unique();
            $t->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $t->string('category', 40); // complaint, inquiry, suggestion
            $t->string('subject');
            $t->text('message');
            $t->enum('status', ['open', 'in_progress', 'resolved', 'closed'])->default('open');
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->text('response')->nullable();
            $t->timestamp('resolved_at')->nullable();
            $t->timestamps();
        });

        /* ---------- Notifications ---------- */
        Schema::create('message_logs', function (Blueprint $t) {
            $t->id();
            $t->enum('channel', ['sms', 'email', 'whatsapp']);
            $t->string('recipient');
            $t->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $t->text('message');
            $t->string('subject')->nullable();
            $t->enum('status', ['sent', 'failed', 'logged'])->default('sent');
            $t->string('batch', 40)->nullable();
            $t->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index('batch');
        });

        /* ---------- Governance ---------- */
        Schema::create('committee_members', function (Blueprint $t) {
            $t->id();
            $t->foreignId('member_id')->constrained();
            $t->string('committee', 40); // board, supervisory, credit, education, audit
            $t->string('position', 60);
            $t->date('from_date');
            $t->date('to_date')->nullable();
            $t->timestamps();
        });

        Schema::create('meetings', function (Blueprint $t) {
            $t->id();
            $t->string('type', 30); // board, committee, agm, sgm
            $t->string('title');
            $t->dateTime('scheduled_at');
            $t->string('venue')->nullable();
            $t->text('agenda')->nullable();
            $t->longText('minutes')->nullable();
            $t->enum('status', ['scheduled', 'held', 'cancelled'])->default('scheduled');
            $t->unsignedInteger('attendance')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('resolutions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $t->string('number', 30);
            $t->string('title');
            $t->text('details')->nullable();
            $t->enum('status', ['passed', 'rejected', 'deferred', 'implemented'])->default('passed');
            $t->timestamps();
        });

        /* ---------- Fixed assets ---------- */
        Schema::create('fixed_assets', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();
            $t->string('name');
            $t->string('category', 60);
            $t->string('location')->nullable();
            $t->string('custodian')->nullable();
            $t->date('purchase_date');
            $t->decimal('cost', 16, 2);
            $t->decimal('salvage_value', 16, 2)->default(0);
            $t->unsignedSmallInteger('useful_life_years')->default(5);
            $t->enum('method', ['straight_line', 'reducing_balance'])->default('straight_line');
            $t->decimal('rate', 6, 2)->default(0); // for reducing balance, % p.a.
            $t->decimal('accumulated_depreciation', 16, 2)->default(0);
            $t->date('last_depreciated_on')->nullable();
            $t->date('next_maintenance_on')->nullable();
            $t->enum('status', ['active', 'disposed'])->default('active');
            $t->date('disposed_on')->nullable();
            $t->decimal('disposal_amount', 16, 2)->nullable();
            $t->timestamps();
        });

        /* ---------- Security ---------- */
        Schema::table('users', function (Blueprint $t) {
            $t->string('otp_code')->nullable();
            $t->timestamp('otp_expires_at')->nullable();
            $t->timestamp('password_changed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['otp_code', 'otp_expires_at', 'password_changed_at']));
        foreach (['fixed_assets', 'resolutions', 'meetings', 'committee_members', 'message_logs', 'tickets', 'withdrawal_requests',
                  'loan_collaterals', 'loan_approvals'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
        Schema::table('loans', function (Blueprint $t) {
            $t->dropConstrainedForeignId('top_up_of');
            $t->dropColumn(['approvals_required', 'credit_score', 'top_up_payoff', 'rescheduled_at', 'reschedule_count']);
        });
        foreach (['standing_orders', 'fixed_deposits', 'employer_remittances', 'checkoff_lines', 'checkoff_schedules',
                  'member_change_requests', 'member_documents'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
        Schema::table('members', function (Blueprint $t) {
            $t->dropConstrainedForeignId('member_category_id');
            $t->dropConstrainedForeignId('employer_id');
            $t->dropConstrainedForeignId('approved_by');
            $t->dropColumn(['approved_at', 'exit_reason', 'occupation', 'source_of_funds', 'risk_rating', 'is_pep', 'data_consent_at']);
        });
        Schema::dropIfExists('employers');
        Schema::dropIfExists('member_categories');
        Schema::table('expenses', function (Blueprint $t) {
            $t->dropConstrainedForeignId('gl_account_id');
            $t->dropConstrainedForeignId('paid_from_gl_id');
            $t->dropColumn('is_paid');
        });
        Schema::table('account_types', function (Blueprint $t) {
            $t->dropConstrainedForeignId('gl_account_id');
            $t->dropColumn(['interest_rate', 'monthly_minimum']);
        });
        foreach (['bank_reconciliations', 'bank_accounts', 'journal_lines', 'journal_entries', 'gl_accounts'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
