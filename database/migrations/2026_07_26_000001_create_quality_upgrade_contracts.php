<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quality_upgrade_contracts', function (Blueprint $table): void {
            $table->id();
            $table->string('contract_no')->unique();
            $table->date('contract_date');
            $table->foreignId('costumer_id')->constrained('costumers');
            $table->foreignId('detail_rumah_id')->constrained('detail_rumahs');
            $table->foreignId('spr_id')->nullable()->constrained('sprs')->nullOnDelete();
            $table->foreignId('company_id')->constrained('cabang_perusahaans');
            $table->foreignId('master_bank_id')->nullable()->constrained('master_banks')->nullOnDelete();
            $table->string('payment_method', 30);
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('discount', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('contract_value', 18, 2)->default(0);
            $table->decimal('down_payment', 18, 2)->default(0);
            $table->unsignedSmallInteger('installment_count')->default(1);
            $table->date('planned_start_date')->nullable();
            $table->date('planned_finish_date')->nullable();
            $table->unsignedSmallInteger('warranty_days')->default(0);
            $table->string('business_status', 40)->default('draft');
            $table->json('company_snapshot')->nullable();
            $table->json('customer_snapshot')->nullable();
            $table->json('unit_snapshot')->nullable();
            $table->json('payment_snapshot')->nullable();
            $table->text('scope_notes')->nullable();
            $table->text('terms')->nullable();
            $table->string('record_status', 20)->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'business_status']);
            $table->index(['detail_rumah_id', 'costumer_id']);
        });

        Schema::create('quality_upgrade_contract_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('quality_upgrade_contract_id');
            $table->foreign('quality_upgrade_contract_id', 'qu_item_contract_fk')->references('id')->on('quality_upgrade_contracts')->cascadeOnDelete();
            $table->string('item_code')->nullable();
            $table->string('name');
            $table->text('specification')->nullable();
            $table->string('location')->nullable();
            $table->decimal('volume', 18, 4);
            $table->string('unit', 30);
            $table->decimal('unit_price', 18, 2);
            $table->decimal('discount', 18, 2)->default(0);
            $table->decimal('total', 18, 2);
            $table->decimal('progress_percent', 5, 2)->default(0);
            $table->decimal('material_cost', 18, 2)->default(0);
            $table->decimal('labor_cost', 18, 2)->default(0);
            $table->decimal('other_cost', 18, 2)->default(0);
            $table->string('work_status', 30)->default('not_started');
            $table->timestamps();
        });

        Schema::table('payment_schedules', function (Blueprint $table): void {
            $table->foreignId('quality_upgrade_contract_id')->nullable()->after('sales_transaction_id')->constrained()->nullOnDelete();
            $table->index(['quality_upgrade_contract_id', 'status'], 'pay_sched_upgrade_status_idx');
        });

        Schema::table('customer_receipts', function (Blueprint $table): void {
            $table->foreignId('quality_upgrade_contract_id')->nullable()->after('housing_reservation_id')->constrained()->nullOnDelete();
            $table->index(['quality_upgrade_contract_id', 'status'], 'receipt_upgrade_status_idx');
        });

        Schema::table('journals', function (Blueprint $table): void {
            $table->foreignId('cabang_perusahaan_id')->nullable()->after('source_id')->constrained('cabang_perusahaans')->nullOnDelete();
            $table->index(['cabang_perusahaan_id', 'tanggal'], 'journal_company_date_idx');
        });

        foreach ([
            ['1-1500', 'Piutang Penambahan Mutu', 'aset', 'debit'],
            ['2-2300', 'Uang Muka Penambahan Mutu', 'kewajiban', 'kredit'],
            ['4-1300', 'Pendapatan Penambahan Mutu', 'pendapatan', 'kredit'],
            ['5-1300', 'Beban Pokok Penambahan Mutu', 'beban', 'debit'],
        ] as [$code, $name, $category, $normal]) {
            DB::table('chart_of_accounts')->updateOrInsert(
                ['kode_akun' => $code],
                ['nama_akun' => $name, 'kategori' => $category, 'posisi_normal' => $normal, 'status' => 'aktif', 'is_system' => true, 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    public function down(): void
    {
        Schema::table('journals', function (Blueprint $table): void {
            $table->dropIndex('journal_company_date_idx');
            $table->dropConstrainedForeignId('cabang_perusahaan_id');
        });
        Schema::table('customer_receipts', function (Blueprint $table): void {
            $table->dropIndex('receipt_upgrade_status_idx');
            $table->dropConstrainedForeignId('quality_upgrade_contract_id');
        });
        Schema::table('payment_schedules', function (Blueprint $table): void {
            $table->dropIndex('pay_sched_upgrade_status_idx');
            $table->dropConstrainedForeignId('quality_upgrade_contract_id');
        });
        Schema::dropIfExists('quality_upgrade_contract_items');
        Schema::dropIfExists('quality_upgrade_contracts');
    }
};
