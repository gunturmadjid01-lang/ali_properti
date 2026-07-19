<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_process_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_transaction_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->string('code');
            $table->string('label');
            $table->string('category');
            $table->text('description')->nullable();
            $table->date('planned_date')->nullable();
            $table->date('actual_date')->nullable();
            $table->string('status')->default('waiting');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_original_name')->nullable();
            $table->string('record_status')->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['sales_transaction_id', 'code']);
        });
        DB::table('approval_settings')->insertOrIgnore(['module_key' => 'sales-process-step', 'module_label' => 'Tahapan Penjualan sampai Customer Menempati Unit', 'action' => 'lock', 'requires_approval' => false, 'approval_stages' => 0, 'approval_steps' => json_encode([]), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        foreach (['sales-process.view', 'sales-process.update', 'sales-process.lock', 'sales-process.unlock', 'sales-process.print'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        $permissions = Permission::whereIn('name', ['sales-process.view', 'sales-process.update', 'sales-process.lock', 'sales-process.unlock', 'sales-process.print'])->get();
        Role::whereIn('name', ['owner', 'manager', 'marketing', 'area_marketing', 'admin_marketing', 'keuangan', 'admin_keuangan', 'pengawas', 'admin'])->get()->each(fn ($role) => $role->givePermissionTo($permissions));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_process_steps');
    }
};
