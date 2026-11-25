<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('name', 40)->unique();       // slug stored in users.role
            $t->string('label', 80);
            $t->string('description')->nullable();
            $t->boolean('is_system')->default(false); // built-in roles can't be deleted or renamed
            $t->timestamps();
        });
        Schema::create('permissions', function (Blueprint $t) {
            $t->id();
            $t->string('name', 60)->unique();
            $t->string('label');
            $t->string('group', 60);
            $t->timestamps();
        });
        Schema::create('permission_role', function (Blueprint $t) {
            $t->foreignId('role_id')->constrained()->cascadeOnDelete();
            $t->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $t->primary(['role_id', 'permission_id']);
        });
        // extra roles on top of users.role (e.g. a teller who also handles welfare)
        Schema::create('role_user', function (Blueprint $t) {
            $t->foreignId('role_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->primary(['role_id', 'user_id']);
        });

        $now = now();
        foreach ([
            ['admin', 'Administrator', 'Full access to everything, including users, roles and settings.'],
            ['manager', 'Manager / Credit Committee', 'Approvals, credit committee, welfare decisions, governance and reports.'],
            ['loan_officer', 'Loan Officer', 'Loan applications, appraisal, guarantors, collateral and follow-up.'],
            ['accountant', 'Accountant', 'Accounting, disbursements, check-off, dividends, expenses and assets.'],
            ['teller', 'Teller / Cashier', 'Counter transactions, repayments, withdrawals and M-Pesa.'],
            ['member', 'Member', 'Member portal only.'],
        ] as [$name, $label, $desc]) {
            DB::table('roles')->insert(['name' => $name, 'label' => $label, 'description' => $desc, 'is_system' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        \App\Support\Permissions::sync(true);
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
