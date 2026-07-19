<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_kpr_financings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('kpr_submission_id')->unique()->constrained()->cascadeOnDelete();
            $t->decimal('sale_price', 18, 2);
            $t->decimal('approved_limit', 18, 2)->default(0);
            $t->unsignedSmallInteger('tenor_months')->nullable();
            $t->decimal('interest_rate', 8, 4)->nullable();
            $t->decimal('booking_fee', 18, 2)->default(0);
            $t->decimal('down_payment', 18, 2)->default(0);
            $t->decimal('shortfall', 18, 2)->default(0);
            $t->decimal('developer_fee', 18, 2)->default(0);
            $t->decimal('notary_fee', 18, 2)->default(0);
            $t->date('expected_disbursement_date')->nullable();
            $t->string('sp3k_number')->nullable();
            $t->date('sp3k_date')->nullable();
            $t->date('sp3k_expired_at')->nullable();
            $t->text('notes')->nullable();
            $t->string('record_status')->default('draft');
            $t->timestamp('locked_at')->nullable();
            $t->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('bank_kpr_disbursements', function (Blueprint $t) {
            $t->id();
            $t->string('disbursement_no')->unique();
            $t->foreignId('kpr_submission_id')->constrained()->cascadeOnDelete();
            $t->foreignId('master_bank_id')->nullable()->constrained('master_banks')->nullOnDelete();
            $t->date('disbursement_date');
            $t->decimal('amount', 18, 2);
            $t->string('bank_reference')->nullable();
            $t->string('proof_path')->nullable();
            $t->string('proof_original_name')->nullable();
            $t->text('notes')->nullable();
            $t->string('status')->default('draft');
            $t->string('record_status')->default('draft');
            $t->timestamp('locked_at')->nullable();
            $t->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('customer_receipt_id')->nullable()->constrained('customer_receipts')->nullOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
        });
        foreach (['bank-kpr-financing' => 'Struktur Pembiayaan KPR Bank', 'bank-kpr-disbursement' => 'Pencairan KPR Bank'] as $key => $label) {
            DB::table('approval_settings')->insertOrIgnore(['module_key' => $key, 'module_label' => $label, 'action' => 'lock', 'requires_approval' => false, 'approval_stages' => 0, 'approval_steps' => json_encode([]), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach (['bank-kpr.financing.view', 'bank-kpr.financing.create', 'bank-kpr.financing.submit', 'bank-kpr.disbursement.view', 'bank-kpr.disbursement.create', 'bank-kpr.disbursement.submit', 'bank-kpr.disbursement.print'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_kpr_disbursements');
        Schema::dropIfExists('bank_kpr_financings');
    }
};
