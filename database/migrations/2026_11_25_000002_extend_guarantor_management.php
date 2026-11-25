<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guarantor register on the member record
        Schema::table('members', function (Blueprint $t) {
            $t->string('guarantor_status', 12)->default('eligible');   // eligible | suspended | blocked
            $t->string('guarantor_note')->nullable();
            $t->decimal('max_guarantee_amount', 16, 2)->nullable();     // per-member limit (overrides the general rule)
            $t->timestamp('guarantor_registered_at')->nullable();      // registered / accepted guarantor terms
        });

        // statuses: pending | accepted | declined | expired | released | withdrawn
        DB::statement("ALTER TABLE loan_guarantors MODIFY status VARCHAR(15) NOT NULL DEFAULT 'pending'");

        Schema::table('loan_guarantors', function (Blueprint $t) {
            $t->string('consent_channel', 12)->nullable();             // portal | esign | paper | staff
            $t->string('consent_ip', 45)->nullable();
            $t->string('decline_reason')->nullable();
            $t->timestamp('expires_at')->nullable();                   // pending requests lapse after N days
            $t->timestamp('released_at')->nullable();
            $t->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('release_reason')->nullable();
            $t->timestamp('release_requested_at')->nullable();         // guarantor asks to be released
            $t->string('release_request_reason')->nullable();
            $t->decimal('recovered_amount', 16, 2)->default(0);        // taken from this guarantor on default
            $t->foreignId('replaced_by_id')->nullable();               // the guarantor row that replaced this one
        });

        DB::table('loan_guarantors')->where('status', 'released')->whereNull('released_at')->update(['released_at' => DB::raw('updated_at')]);
        DB::table('loan_guarantors')->where('status', 'accepted')->whereNull('consent_channel')->update(['consent_channel' => 'portal']);
    }

    public function down(): void
    {
        Schema::table('loan_guarantors', function (Blueprint $t) {
            $t->dropConstrainedForeignId('released_by');
            $t->dropColumn(['consent_channel', 'consent_ip', 'decline_reason', 'expires_at', 'released_at', 'release_reason',
                'release_requested_at', 'release_request_reason', 'recovered_amount', 'replaced_by_id']);
        });
        Schema::table('members', fn (Blueprint $t) => $t->dropColumn(['guarantor_status', 'guarantor_note', 'max_guarantee_amount', 'guarantor_registered_at']));
    }
};
