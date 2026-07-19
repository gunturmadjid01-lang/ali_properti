<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('batch_number', 50)->unique();
            $table->string('period', 7)->index();
            $table->date('payment_date');
            $table->text('notes')->nullable();
            $table->decimal('total_gross', 18, 2)->default(0);
            $table->decimal('total_deductions', 18, 2)->default(0);
            $table->decimal('total_net', 18, 2)->default(0);
            $table->string('status', 30)->default('draft')->index();
            $table->string('record_status', 20)->default('draft')->index();
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payroll_batch_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('employee_number')->nullable();
            $table->string('employee_name');
            $table->string('job_position');
            $table->decimal('basic_salary', 18, 2);
            $table->decimal('fixed_allowance', 18, 2)->default(0);
            $table->decimal('other_allowance', 18, 2)->default(0);
            $table->decimal('deductions', 18, 2)->default(0);
            $table->decimal('net_salary', 18, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['payroll_batch_id', 'user_id']);
        });

        if (Schema::hasTable('approval_settings')) {
            DB::table('approval_settings')->updateOrInsert(
                ['module_key' => 'employee-payroll', 'action' => 'lock'],
                ['module_label' => 'Penggajian Pegawai', 'is_active' => true, 'requires_approval' => false, 'approval_stages' => 0, 'approval_steps' => '[]', 'approver_role_ids' => '[]', 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    public function down(): void
    {
        DB::table('approval_settings')->where(['module_key' => 'employee-payroll', 'action' => 'lock'])->delete();
        Schema::dropIfExists('payroll_batch_items');
        Schema::dropIfExists('payroll_batches');
    }
};
