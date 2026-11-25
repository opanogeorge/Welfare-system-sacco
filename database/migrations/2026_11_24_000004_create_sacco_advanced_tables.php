<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* ---------- Multi-branch ---------- */
        Schema::create('branches', function (Blueprint $t) {
            $t->id();
            $t->string('code', 10)->unique();
            $t->string('name');
            $t->string('location')->nullable();
            $t->string('phone', 20)->nullable();
            $t->boolean('is_hq')->default(false);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        foreach (['users', 'members', 'loans', 'transactions', 'journal_entries', 'expenses'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            });
        }

        /* ---------- Multi-SACCO (tenants live in the main database) ---------- */
        Schema::create('tenants', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();
            $t->string('name');
            $t->string('domain')->unique();          // e.g. kenya-teachers.saccosystem.co.ke
            $t->string('database')->unique();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        /* ---------- Credit risk model ---------- */
        Schema::create('risk_models', function (Blueprint $t) {
            $t->id();
            $t->string('version', 30);
            $t->unsignedInteger('samples');
            $t->unsignedInteger('defaults');
            $t->json('features');      // names, means, stds
            $t->json('weights');
            $t->decimal('intercept', 12, 6);
            $t->decimal('auc', 6, 4)->nullable();
            $t->decimal('accuracy', 6, 4)->nullable();
            $t->json('metrics')->nullable();
            $t->boolean('is_active')->default(false);
            $t->foreignId('trained_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::table('loans', function (Blueprint $t) {
            $t->json('risk_features')->nullable();          // snapshot at application time
            $t->decimal('pd', 6, 4)->nullable();            // probability of default
            $t->string('risk_band', 2)->nullable();
            $t->foreignId('risk_model_id')->nullable()->constrained('risk_models')->nullOnDelete();
            $t->unsignedSmallInteger('max_days_arrears')->default(0); // worst arrears ever (training label)
        });

        /* ---------- Fraud & suspicious-activity monitoring ---------- */
        Schema::create('fraud_alerts', function (Blueprint $t) {
            $t->id();
            $t->string('rule', 40);
            $t->enum('severity', ['low', 'medium', 'high']);
            $t->unsignedTinyInteger('score')->default(50);
            $t->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('staff_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('summary');
            $t->json('details')->nullable();
            $t->enum('status', ['open', 'investigating', 'cleared', 'reported'])->default('open');
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('review_notes')->nullable();
            $t->string('str_reference', 60)->nullable();   // FRC suspicious transaction report ref
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'severity']);
            $t->index(['rule', 'member_id', 'created_at']);
        });

        /* ---------- Credit reference bureau ---------- */
        Schema::create('crb_checks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('member_id')->constrained()->cascadeOnDelete();
            $t->foreignId('loan_id')->nullable()->constrained()->nullOnDelete();
            $t->string('provider', 30);          // metropol, transunion, creditinfo, manual
            $t->string('check_type', 30);        // score, delinquency, identity, report
            $t->unsignedSmallInteger('score')->nullable();
            $t->string('grade', 10)->nullable();
            $t->boolean('is_listed')->nullable(); // negative listing
            $t->string('summary')->nullable();
            $t->json('response')->nullable();
            $t->string('report_path')->nullable();
            $t->boolean('consent_obtained')->default(false);
            $t->string('status', 20)->default('success'); // success, failed
            $t->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        /* ---------- Partner API & webhooks ---------- */
        Schema::create('api_clients', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('key_prefix', 12)->index();
            $t->string('key_hash', 64)->unique();
            $t->json('scopes');
            $t->string('allowed_ips')->nullable();
            $t->string('webhook_url')->nullable();
            $t->text('webhook_secret')->nullable(); // encrypted
            $t->json('events')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamp('last_used_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('api_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('api_client_id')->nullable()->constrained()->nullOnDelete();
            $t->string('method', 8);
            $t->string('path');
            $t->unsignedSmallInteger('status');
            $t->string('ip', 45)->nullable();
            $t->unsignedInteger('duration_ms')->default(0);
            $t->timestamp('created_at')->nullable();
            $t->index(['api_client_id', 'created_at']);
        });
        Schema::create('partner_payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('api_client_id')->constrained();
            $t->string('reference', 60);
            $t->string('target', 40);           // member no / loan no
            $t->string('account', 20)->nullable();
            $t->decimal('amount', 14, 2);
            $t->string('status', 20);           // posted, failed
            $t->string('message')->nullable();
            $t->foreignId('transaction_id')->nullable();
            $t->timestamps();
            $t->unique(['api_client_id', 'reference']);
        });
        Schema::create('webhook_deliveries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('api_client_id')->constrained()->cascadeOnDelete();
            $t->string('event', 40);
            $t->json('payload');
            $t->unsignedSmallInteger('response_code')->nullable();
            $t->text('response_body')->nullable();
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->timestamp('next_attempt_at')->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->timestamps();
            $t->index(['delivered_at', 'next_attempt_at']);
        });

        /* ---------- E-signatures ---------- */
        Schema::create('signatures', function (Blueprint $t) {
            $t->id();
            $t->string('signable_type');
            $t->unsignedBigInteger('signable_id');
            $t->string('purpose', 40);            // membership_application, loan_agreement, guarantee
            $t->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('signer_name');
            $t->string('signer_role', 30);
            $t->string('image_path');
            $t->string('document_hash', 64);
            $t->string('verification_code', 20)->unique();
            $t->string('ip', 45)->nullable();
            $t->string('user_agent')->nullable();
            $t->boolean('otp_verified')->default(false);
            $t->timestamp('signed_at');
            $t->timestamps();
            $t->index(['signable_type', 'signable_id']);
        });

        /* ---------- Identity verification ---------- */
        Schema::create('identity_verifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('member_id')->constrained()->cascadeOnDelete();
            $t->string('provider', 30);           // smileid, manual
            $t->string('type', 30);               // iprs_match, face_match, document
            $t->enum('result', ['verified', 'partial', 'failed', 'pending']);
            $t->decimal('confidence', 5, 2)->nullable();
            $t->json('details')->nullable();
            $t->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::table('members', function (Blueprint $t) {
            $t->string('onboarding_channel', 20)->default('branch'); // branch, online, api
            $t->string('identity_status', 20)->default('unverified'); // unverified, verified, failed
            $t->boolean('crb_consent')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('members', fn (Blueprint $t) => $t->dropColumn(['onboarding_channel', 'identity_status', 'crb_consent']));
        foreach (['identity_verifications', 'signatures', 'webhook_deliveries', 'partner_payments', 'api_logs', 'api_clients', 'crb_checks', 'fraud_alerts'] as $x) {
            Schema::dropIfExists($x);
        }
        Schema::table('loans', function (Blueprint $t) {
            $t->dropConstrainedForeignId('risk_model_id');
            $t->dropColumn(['risk_features', 'pd', 'risk_band', 'max_days_arrears']);
        });
        Schema::dropIfExists('risk_models');
        Schema::dropIfExists('tenants');
        foreach (['users', 'members', 'loans', 'transactions', 'journal_entries', 'expenses'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropConstrainedForeignId('branch_id'));
        }
        Schema::dropIfExists('branches');
    }
};
