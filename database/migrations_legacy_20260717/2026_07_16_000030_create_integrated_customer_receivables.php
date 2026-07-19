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
        Schema::table('payment_schedules', function (Blueprint $table) {
            $table->string('invoice_no')->nullable()->unique()->after('id');
            $table->date('issued_at')->nullable()->after('description');
            $table->string('record_status')->default('draft')->after('status');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
        });

        foreach (['cash_installment_contracts', 'developer_kpr_applications'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('record_status')->default('draft');
                $table->timestamp('locked_at')->nullable();
                $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            });
        }

        Schema::create('receivable_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('perumahan_id')->nullable()->constrained('perumahans')->cascadeOnDelete();
            $table->string('payment_method')->nullable();
            $table->unsignedSmallInteger('warning_days')->default(14);
            $table->unsignedSmallInteger('urgent_days')->default(3);
            $table->unsignedSmallInteger('issue_days_before_due')->default(14);
            $table->unsignedSmallInteger('grace_period_days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['perumahan_id', 'payment_method'], 'receivable_setting_scope_unique');
        });

        Schema::create('customer_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_no')->unique();
            $table->foreignId('sales_transaction_id')->constrained()->restrictOnDelete();
            $table->foreignId('master_bank_id')->nullable()->constrained('master_banks')->nullOnDelete();
            $table->date('payment_date');
            $table->decimal('amount', 18, 2);
            $table->string('payment_method');
            $table->string('bank_reference')->nullable();
            $table->string('sender_bank')->nullable();
            $table->string('sender_name')->nullable();
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
            $table->foreignId('financial_transaction_id')->nullable()->constrained('transaksi_keuangans')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('customer_receipt_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 18, 2);
            $table->string('allocation_type')->default('invoice');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        foreach (['customer-receipt' => 'Penerimaan Customer', 'cash-installment-contract' => 'Kontrak Cash Bertahap', 'developer-kpr-contract' => 'Kontrak KPR Developer'] as $key => $label) {
            DB::table('approval_settings')->insertOrIgnore([
                'module_key' => $key, 'module_label' => $label, 'action' => 'lock',
                'requires_approval' => false, 'approval_stages' => 0, 'approval_steps' => json_encode([]),
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        foreach (['receivables.view', 'receivables.print', 'customer-receipts.view', 'customer-receipts.create', 'customer-receipts.update', 'customer-receipts.lock', 'customer-receipts.unlock', 'customer-receipts.print', 'sales.transaction-detail.summary.view', 'sales.transaction-detail.schedules.view', 'sales.transaction-detail.payments.view', 'sales.transaction-detail.construction.view', 'sales.transaction-detail.handover.view', 'sales.transaction-detail.after-sales.view', 'sales.transaction-detail.history.view'] as $name) {
            Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        $financePermissions = Permission::query()->whereIn('name', ['receivables.view', 'receivables.print', 'customer-receipts.view', 'customer-receipts.create', 'customer-receipts.update', 'customer-receipts.lock', 'customer-receipts.unlock', 'customer-receipts.print'])->get();
        Role::query()->whereIn('name', ['keuangan', 'admin_keuangan'])->get()->each(fn (Role $role) => $role->givePermissionTo($financePermissions));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_receipt_allocations');
        Schema::dropIfExists('customer_receipts');
        Schema::dropIfExists('receivable_settings');
        foreach (['cash_installment_contracts', 'developer_kpr_applications'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['record_status', 'locked_at', 'locked_by']));
        }
        Schema::table('payment_schedules', function (Blueprint $table) {
            $table->dropUnique(['invoice_no']);
            $table->dropColumn(['invoice_no', 'issued_at', 'record_status', 'locked_at', 'locked_by']);
        });
    }
};
