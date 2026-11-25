<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // flat | reducing (equal installments) | equal_principal (declining installments)
        Schema::table('loan_products', function (Blueprint $t) {
            $t->string('interest_method', 20)->change();
            $t->unsignedTinyInteger('grace_period_months')->default(0);   // months before first installment
            $t->unsignedTinyInteger('max_active_per_member')->default(0); // 0 = unlimited
            $t->boolean('allow_top_up')->default(true);
            $t->boolean('collateral_required')->default(false);
            $t->decimal('max_deduction_ratio', 5, 2)->default(66.67);     // % of gross pay all deductions may take (1/3 rule)
        });
        Schema::table('loans', function (Blueprint $t) {
            $t->string('interest_method', 20)->change();
            $t->unsignedTinyInteger('grace_period_months')->default(0);
            // Affordability appraisal (payslip)
            $t->decimal('gross_pay', 14, 2)->nullable();
            $t->decimal('net_pay', 14, 2)->nullable();
            $t->decimal('other_deductions', 14, 2)->nullable();
            $t->decimal('affordability_ratio', 6, 2)->nullable();          // % of gross taken by all deductions incl. new installment
            // Refinance & write-off
            $t->boolean('is_refinance')->default(false);
            $t->date('written_off_on')->nullable();
            $t->decimal('written_off_amount', 16, 2)->default(0);
            $t->decimal('recovered_after_write_off', 16, 2)->default(0);
            $t->foreignId('written_off_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('recovery_stage', 30)->nullable();                  // reminder, demand, guarantors, auctioneer, legal, written_off
        });

        // Income account for money recovered on written-off loans
        if (Schema::hasTable('gl_accounts') && ! \Illuminate\Support\Facades\DB::table('gl_accounts')->where('system_key', 'bad_debt_recovered')->exists()) {
            \Illuminate\Support\Facades\DB::table('gl_accounts')->insert(['code' => '4070', 'name' => 'Bad Debts Recovered', 'type' => 'income',
                'system_key' => 'bad_debt_recovered', 'is_cash' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }

        Schema::create('loan_charges', function (Blueprint $t) {
            $t->id();
            $t->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $t->enum('kind', ['fine', 'waiver']);
            $t->string('category', 40);           // late_penalty, bounced_cheque, legal_fees, collection_costs, other
            $t->decimal('amount', 14, 2);
            $t->string('reason');
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('loan_recovery_actions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $t->string('action', 30);             // call, sms, visit, demand_letter, promise_to_pay, guarantor_notice, guarantor_recovery, auctioneer, legal, note
            $t->text('notes')->nullable();
            $t->date('promise_date')->nullable();
            $t->decimal('promise_amount', 14, 2)->nullable();
            $t->string('promise_status', 20)->nullable(); // open, kept, broken
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
            $t->index(['promise_status', 'promise_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_recovery_actions');
        Schema::dropIfExists('loan_charges');
        Schema::table('loans', function (Blueprint $t) {
            $t->dropConstrainedForeignId('written_off_by');
            $t->dropColumn(['grace_period_months', 'gross_pay', 'net_pay', 'other_deductions', 'affordability_ratio', 'is_refinance',
                'written_off_on', 'written_off_amount', 'recovered_after_write_off', 'recovery_stage']);
        });
        Schema::table('loan_products', fn (Blueprint $t) => $t->dropColumn(['grace_period_months', 'max_active_per_member', 'allow_top_up', 'collateral_required', 'max_deduction_ratio']));
    }
};
