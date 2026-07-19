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
        Schema::create('customer_charges', function (Blueprint $table) {
            $table->id();
            $table->string('charge_no')->unique();
            $table->foreignId('sales_transaction_id')->constrained()->restrictOnDelete();
            $table->foreignId('master_bank_id')->nullable()->constrained('master_banks')->nullOnDelete();
            $table->string('charge_type');
            $table->string('category');
            $table->string('description');
            $table->decimal('amount', 18, 2);
            $table->date('charge_date');
            $table->date('due_date');
            $table->string('paid_to')->nullable();
            $table->string('payment_reference')->nullable();
            $table->string('proof_path')->nullable();
            $table->string('proof_original_name')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('draft');
            $table->string('record_status')->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('journal_id')->nullable()->constrained('journals')->nullOnDelete();
            $table->string('reversal_status')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reversal_journal_id')->nullable()->constrained('journals')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'due_date']);
            $table->index(['sales_transaction_id', 'charge_type']);
        });

        foreach (['customer-charge' => 'Tagihan Tambahan & Talangan Customer', 'customer-charge-reversal' => 'Reversal Tagihan/Talangan Customer'] as $key => $label) {
            DB::table('approval_settings')->insertOrIgnore([
                'module_key' => $key, 'module_label' => $label, 'action' => 'lock',
                'requires_approval' => false, 'approval_stages' => 0, 'approval_steps' => json_encode([]),
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $names = collect(['view', 'create', 'update', 'lock', 'unlock', 'print', 'reverse'])->map(fn ($action) => "customer-charges.{$action}");
        $names->each(fn ($name) => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        $permissions = Permission::whereIn('name', $names)->get();
        Role::whereIn('name', ['owner', 'manager', 'keuangan', 'admin_keuangan', 'super_admin'])->get()->each(fn (Role $role) => $role->givePermissionTo($permissions));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_charges');
        DB::table('approval_settings')->whereIn('module_key', ['customer-charge', 'customer-charge-reversal'])->delete();
        Permission::whereIn('name', ['customer-charges.view', 'customer-charges.create', 'customer-charges.update', 'customer-charges.lock', 'customer-charges.unlock', 'customer-charges.print', 'customer-charges.reverse'])->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
