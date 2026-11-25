<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('member_no', 20)->unique();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('national_id', 20)->unique();
            $table->string('kra_pin', 20)->nullable();
            $table->string('phone', 20);
            $table->string('email')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('address')->nullable();
            $table->string('employer')->nullable();
            $table->string('payroll_no', 30)->nullable();
            $table->string('department')->nullable();
            $table->decimal('monthly_contribution', 14, 2)->default(0);
            $table->date('joined_on');
            // pending | active | dormant | suspended | exited
            $table->string('status', 20)->default('pending');
            $table->date('exited_on')->nullable();
            $table->string('photo')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'last_name']);
        });

        Schema::create('member_nominees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('relationship', 50);
            $table->string('national_id', 20)->nullable();
            $table->string('phone', 20)->nullable();
            $table->decimal('percentage', 5, 2)->default(100);
            $table->timestamps();
        });

        // share_capital | deposits (BOSA) | savings (FOSA) | holiday | junior etc.
        Schema::create('account_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->boolean('withdrawable')->default(true);
            $table->boolean('counts_for_loan')->default(false); // used for loan multiplier
            $table->boolean('earns_dividend')->default(false);  // share capital
            $table->boolean('earns_interest')->default(false);  // interest on deposits
            $table->decimal('min_balance', 14, 2)->default(0);
            $table->decimal('withdrawal_fee', 14, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('member_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_type_id')->constrained();
            $table->string('account_no', 30)->unique();
            $table->decimal('balance', 16, 2)->default(0);
            $table->decimal('frozen_amount', 16, 2)->default(0); // e.g. guarantee locks
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['member_id', 'account_type_id']);
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->index();
            $table->foreignId('member_id')->constrained();
            $table->foreignId('member_account_id')->nullable()->constrained();
            $table->foreignId('loan_id')->nullable();
            // deposit | withdrawal | transfer_in | transfer_out | loan_disbursement |
            // loan_repayment | fee | dividend | interest | penalty | reversal
            $table->string('type', 30);
            $table->enum('direction', ['credit', 'debit']);
            $table->decimal('amount', 16, 2);
            $table->decimal('balance_after', 16, 2)->nullable();
            // cash | mpesa | bank | payroll | cheque | internal
            $table->string('channel', 20)->default('cash');
            $table->string('external_ref', 60)->nullable(); // M-Pesa code, cheque no.
            $table->string('description')->nullable();
            $table->date('txn_date');
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('reversed')->default(false);
            $table->timestamps();
            $table->index(['member_id', 'txn_date']);
            $table->index(['type', 'txn_date']);
        });

        Schema::create('loan_products', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('interest_rate', 6, 2);            // % per annum
            $table->enum('interest_method', ['flat', 'reducing']);
            $table->unsignedSmallInteger('min_period')->default(1);  // months
            $table->unsignedSmallInteger('max_period')->default(12);
            $table->decimal('min_amount', 14, 2)->default(1000);
            $table->decimal('max_amount', 14, 2)->default(1000000);
            $table->decimal('deposit_multiplier', 5, 2)->default(3); // x deposits
            $table->decimal('processing_fee_rate', 5, 2)->default(0); // % of principal
            $table->decimal('insurance_rate', 5, 2)->default(0);      // % of principal
            $table->decimal('penalty_rate', 5, 2)->default(0);        // % of overdue installment, monthly
            $table->unsignedTinyInteger('min_guarantors')->default(0);
            $table->unsignedSmallInteger('min_membership_months')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->string('loan_no', 30)->unique();
            $table->foreignId('member_id')->constrained();
            $table->foreignId('loan_product_id')->constrained();
            $table->decimal('amount_applied', 16, 2);
            $table->decimal('amount_approved', 16, 2)->nullable();
            $table->decimal('interest_rate', 6, 2);
            $table->enum('interest_method', ['flat', 'reducing']);
            $table->unsignedSmallInteger('period');
            $table->decimal('processing_fee', 14, 2)->default(0);
            $table->decimal('insurance_fee', 14, 2)->default(0);
            $table->decimal('net_disbursed', 16, 2)->default(0);
            $table->decimal('total_interest', 16, 2)->default(0);
            $table->decimal('total_payable', 16, 2)->default(0);
            $table->decimal('principal_balance', 16, 2)->default(0);
            $table->decimal('interest_balance', 16, 2)->default(0);
            $table->decimal('penalty_balance', 16, 2)->default(0);
            $table->decimal('total_paid', 16, 2)->default(0);
            $table->string('purpose')->nullable();
            // pending | guarantors_pending | appraised | approved | rejected |
            // disbursed | closed | written_off
            $table->string('status', 30)->default('pending');
            $table->text('remarks')->nullable();
            $table->date('applied_on');
            $table->foreignId('appraised_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('appraised_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('disbursed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('disbursed_on')->nullable();
            $table->string('disbursement_channel', 20)->nullable();
            $table->date('first_due_date')->nullable();
            $table->date('maturity_date')->nullable();
            $table->date('closed_on')->nullable();
            $table->timestamps();
            $table->index(['status', 'member_id']);
        });

        Schema::create('loan_guarantors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained();
            $table->decimal('amount', 16, 2);
            $table->enum('status', ['pending', 'accepted', 'declined', 'released'])->default('pending');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->unique(['loan_id', 'member_id']);
        });

        Schema::create('loan_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('installment_no');
            $table->date('due_date');
            $table->decimal('principal_due', 16, 2);
            $table->decimal('interest_due', 16, 2);
            $table->decimal('penalty_due', 16, 2)->default(0);
            $table->decimal('total_due', 16, 2);
            $table->decimal('principal_paid', 16, 2)->default(0);
            $table->decimal('interest_paid', 16, 2)->default(0);
            $table->decimal('penalty_paid', 16, 2)->default(0);
            $table->decimal('opening_balance', 16, 2);
            $table->decimal('closing_balance', 16, 2);
            // pending | partial | paid | overdue
            $table->string('status', 20)->default('pending');
            $table->date('paid_on')->nullable();
            $table->date('penalty_applied_on')->nullable();
            $table->timestamps();
            $table->index(['loan_id', 'due_date']);
        });

        Schema::create('loan_repayments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained();
            $table->string('reference', 40);
            $table->decimal('amount', 16, 2);
            $table->decimal('penalty_paid', 16, 2)->default(0);
            $table->decimal('interest_paid', 16, 2)->default(0);
            $table->decimal('principal_paid', 16, 2)->default(0);
            $table->decimal('overpayment', 16, 2)->default(0);
            $table->string('channel', 20)->default('cash');
            $table->string('external_ref', 60)->nullable();
            $table->date('paid_on');
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('dividend_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->decimal('dividend_rate', 6, 2);         // % on share capital
            $table->decimal('interest_rate', 6, 2);         // % interest on deposits
            $table->decimal('withholding_tax_rate', 5, 2)->default(5);
            $table->decimal('total_gross', 16, 2)->default(0);
            $table->decimal('total_tax', 16, 2)->default(0);
            $table->decimal('total_net', 16, 2)->default(0);
            // draft | computed | approved | paid
            $table->string('status', 20)->default('draft');
            // savings | bank | mpesa | share_capital
            $table->string('payout_destination', 20)->default('savings');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('dividend_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dividend_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained();
            $table->decimal('share_balance', 16, 2);
            $table->decimal('avg_deposit_balance', 16, 2);
            $table->decimal('dividend_amount', 16, 2);
            $table->decimal('interest_amount', 16, 2);
            $table->decimal('gross', 16, 2);
            $table->decimal('tax', 16, 2);
            $table->decimal('net', 16, 2);
            $table->boolean('paid')->default(false);
            $table->timestamps();
            $table->unique(['dividend_period_id', 'member_id']);
        });

        Schema::create('mpesa_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('trans_id', 30)->unique();
            $table->string('trans_type', 30)->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('bill_ref', 60)->nullable(); // account number entered by payer
            $table->string('msisdn', 20)->nullable();
            $table->string('payer_name')->nullable();
            $table->timestamp('trans_time')->nullable();
            // received | posted | unmatched | failed
            $table->string('status', 20)->default('received');
            $table->foreignId('transaction_id')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('category', 60);
            $table->string('description');
            $table->decimal('amount', 14, 2);
            $table->date('spent_on');
            $table->string('payee')->nullable();
            $table->string('reference', 60)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60);
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('data')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        foreach ([
            'audit_logs', 'expenses', 'mpesa_transactions', 'dividend_payouts', 'dividend_periods',
            'loan_repayments', 'loan_schedules', 'loan_guarantors', 'loans', 'loan_products',
            'transactions', 'member_accounts', 'account_types', 'member_nominees', 'members', 'settings',
        ] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
