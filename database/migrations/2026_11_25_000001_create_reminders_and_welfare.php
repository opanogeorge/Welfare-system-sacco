<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* ---------- Automated reminders ---------- */
        Schema::create('reminder_rules', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            // loan_due | loan_overdue | loan_maturity | promise_due | contribution_due | contribution_arrears | welfare_due
            $t->string('event', 30);
            $t->integer('days')->default(0);          // meaning depends on event (see ReminderService::EVENTS)
            $t->string('channel', 10)->default('sms'); // sms | email | both
            $t->text('message');
            $t->boolean('notify_guarantors')->default(false);
            $t->text('guarantor_message')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('reminder_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('reminder_rule_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('loan_id')->nullable();
            $t->string('ref_key', 120)->nullable()->unique(); // stops the same reminder going twice
            $t->string('recipient', 20)->default('member');   // member | guarantor
            $t->string('channel', 10)->default('sms');
            $t->text('message');
            $t->string('status', 10)->default('sent');        // sent | failed
            $t->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete(); // set when sent manually
            $t->timestamps();
            $t->index(['loan_id', 'created_at']);
        });

        /* ---------- Welfare / benevolent fund ---------- */
        Schema::create('welfare_benefits', function (Blueprint $t) {
            $t->id();
            $t->string('type', 30);                 // bereavement | wedding | birth | sickness | graduation | other
            $t->string('relationship', 30)->nullable(); // self | spouse | child | parent | sibling ...
            $t->decimal('amount', 14, 2);           // what the fund pays out
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('welfare_events', function (Blueprint $t) {
            $t->id();
            $t->string('event_no', 20)->unique();
            $t->foreignId('member_id')->constrained();     // the member being supported
            $t->string('type', 30);
            $t->string('relationship', 30)->nullable();
            $t->string('title');
            $t->text('details')->nullable();
            $t->date('event_date')->nullable();
            // voluntary: members give what they wish; levy: fixed amount from every member; both
            $t->string('mode', 12)->default('voluntary');
            $t->decimal('levy_amount', 12, 2)->default(0);
            $t->string('levy_source', 10)->default('SAV');  // SAV | DEP | none (members pay themselves)
            $t->decimal('suggested_amount', 12, 2)->default(0);
            $t->decimal('target_amount', 14, 2)->default(0);
            $t->decimal('fund_contribution', 14, 2)->default(0); // benefit paid from the welfare fund reserve
            $t->date('closes_on')->nullable();
            // requested (from portal) | draft | open | closed | paid | rejected
            $t->string('status', 12)->default('draft');
            $t->decimal('raised', 14, 2)->default(0);
            $t->decimal('paid_out', 14, 2)->default(0);
            $t->date('paid_on')->nullable();
            $t->string('payout_channel', 12)->nullable();
            $t->string('payout_ref', 60)->nullable();
            $t->string('document')->nullable();             // burial permit, invitation etc.
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();
        });

        Schema::create('welfare_contributions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('welfare_event_id')->nullable()->constrained()->cascadeOnDelete(); // null = monthly welfare contribution
            $t->foreignId('member_id')->constrained();
            $t->decimal('amount', 12, 2);
            $t->string('kind', 10)->default('voluntary');   // voluntary | levy | monthly
            $t->string('status', 10)->default('paid');      // paid | due | waived
            $t->string('channel', 12)->nullable();          // savings | deposits | mpesa | cash | bank
            $t->string('reference', 60)->nullable();
            $t->string('period', 7)->nullable();            // YYYY-MM for monthly contributions
            $t->timestamp('paid_at')->nullable();
            $t->string('uniq_key', 40)->nullable()->unique(); // L{event}-{member} for levies, M{period}-{member} for monthly
            $t->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['member_id', 'status']);
        });

        $now = now();
        foreach ([['bereavement', 'self', 100000], ['bereavement', 'spouse', 50000], ['bereavement', 'child', 30000], ['bereavement', 'parent', 20000],
                  ['wedding', null, 10000], ['birth', null, 5000], ['sickness', null, 10000], ['disaster', null, 20000]] as [$type, $rel, $amt]) {
            DB::table('welfare_benefits')->insert(['type' => $type, 'relationship' => $rel, 'amount' => $amt, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        foreach (\App\Services\ReminderService::defaults() as [$name, $event, $days, $msg, $g, $gm]) {
            DB::table('reminder_rules')->insert(['name' => $name, 'event' => $event, 'days' => $days, 'message' => $msg, 'notify_guarantors' => $g,
                'guarantor_message' => $gm, 'channel' => 'sms', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        if (Schema::hasTable('gl_accounts') && ! DB::table('gl_accounts')->where('system_key', 'welfare_fund')->exists()) {
            DB::table('gl_accounts')->insert(['code' => '2400', 'name' => 'Welfare / Benevolent Fund', 'type' => 'liability',
                'system_key' => 'welfare_fund', 'is_cash' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('welfare_contributions');
        Schema::dropIfExists('welfare_events');
        Schema::dropIfExists('welfare_benefits');
        Schema::dropIfExists('reminder_logs');
        Schema::dropIfExists('reminder_rules');
    }
};
