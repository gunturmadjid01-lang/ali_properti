<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('employee_number', 50)->nullable()->unique()->after('kantor_cabang_id');
            $table->string('job_title', 100)->nullable()->after('name');
            $table->date('join_date')->nullable()->after('job_title');
            $table->string('employment_type', 30)->default('tetap')->after('join_date');
            $table->string('employment_status', 30)->default('aktif')->after('employment_type');
            $table->boolean('has_login_access')->default(true)->after('employment_status');
            $table->string('tax_number', 50)->nullable()->after('phone');
            $table->string('bpjs_health_number', 50)->nullable()->after('tax_number');
            $table->string('bpjs_employment_number', 50)->nullable()->after('bpjs_health_number');
            $table->string('payroll_bank_name', 100)->nullable()->after('bpjs_employment_number');
            $table->string('payroll_bank_account', 100)->nullable()->after('payroll_bank_name');
            $table->string('payroll_bank_holder', 100)->nullable()->after('payroll_bank_account');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['employee_number']);
            $table->dropColumn([
                'employee_number',
                'job_title',
                'join_date',
                'employment_type',
                'employment_status',
                'has_login_access',
                'tax_number',
                'bpjs_health_number',
                'bpjs_employment_number',
                'payroll_bank_name',
                'payroll_bank_account',
                'payroll_bank_holder',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
        });
    }
};
