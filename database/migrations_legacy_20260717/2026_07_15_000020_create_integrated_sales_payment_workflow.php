<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_installment_schemes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->foreignId('cabang_perusahaan_id')->nullable()->constrained('cabang_perusahaans')->nullOnDelete();
            $table->foreignId('perumahan_id')->nullable()->constrained('perumahans')->nullOnDelete();
            $table->decimal('minimum_booking_fee', 18, 2)->default(0);
            $table->decimal('minimum_dp', 18, 2)->default(0);
            $table->unsignedSmallInteger('installment_count');
            $table->unsignedSmallInteger('maximum_tenor_months');
            $table->string('interval_type')->default('monthly');
            $table->unsignedSmallInteger('grace_period_days')->default(0);
            $table->string('penalty_method')->default('fixed');
            $table->decimal('penalty_value', 18, 4)->default(0);
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->string('status')->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->json('requirements')->nullable();
            $table->text('handover_terms')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('cash_installment_scheme_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_installment_scheme_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->string('name');
            $table->string('calculation_type');
            $table->decimal('value', 18, 4)->default(0);
            $table->unsignedSmallInteger('due_offset_months')->default(0);
            $table->string('required_before')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['cash_installment_scheme_id', 'sequence'], 'cash_scheme_step_sequence_unique');
        });
        Schema::create('developer_kpr_products', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->foreignId('cabang_perusahaan_id')->nullable()->constrained('cabang_perusahaans')->nullOnDelete();
            $table->foreignId('perumahan_id')->nullable()->constrained('perumahans')->nullOnDelete();
            $table->decimal('minimum_dp', 18, 2)->default(0);
            $table->decimal('maximum_financing', 18, 2)->nullable();
            $table->unsignedSmallInteger('minimum_tenor_months');
            $table->unsignedSmallInteger('maximum_tenor_months');
            $table->json('allowed_tenors')->nullable();
            $table->decimal('annual_margin', 8, 4)->default(0);
            $table->string('margin_method')->default('flat');
            $table->decimal('administration_fee', 18, 2)->default(0);
            $table->decimal('contract_fee', 18, 2)->default(0);
            $table->unsignedSmallInteger('grace_period_days')->default(0);
            $table->string('penalty_method')->default('fixed');
            $table->decimal('penalty_value', 18, 4)->default(0);
            $table->decimal('minimum_income', 18, 2)->default(0);
            $table->unsignedSmallInteger('maximum_age')->nullable();
            $table->json('requirements')->nullable();
            $table->text('handover_terms')->nullable();
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->string('status')->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
        if (! Schema::hasTable('sales_transactions')) {
        Schema::create('sales_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_no')->unique();
            $table->foreignId('spr_id')->unique()->constrained('sprs')->restrictOnDelete();
            $table->foreignId('costumer_id')->constrained('costumers')->restrictOnDelete();
            $table->foreignId('cabang_perusahaan_id')->nullable()->constrained('cabang_perusahaans')->nullOnDelete();
            $table->foreignId('perumahan_id')->constrained('perumahans')->restrictOnDelete();
            $table->foreignId('detail_rumah_id')->constrained('detail_rumahs')->restrictOnDelete();
            $table->foreignId('marketing_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('payment_method');
            $table->decimal('sale_price_snapshot', 18, 2);
            $table->json('party_snapshot');
            $table->json('payment_snapshot');
            $table->string('status')->default('active');
            $table->timestamp('approved_at');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
        } else {
            // Instalasi lama sudah memiliki tabel transaksi penjualan dengan
            // penamaan kolom berbeda. Pertahankan datanya dan lengkapi kontrak
            // yang dipakai workflow penjualan terintegrasi.
            Schema::table('sales_transactions', function (Blueprint $table) {
                if (! Schema::hasColumn('sales_transactions', 'transaction_no')) $table->string('transaction_no')->nullable()->unique();
                if (! Schema::hasColumn('sales_transactions', 'cabang_perusahaan_id')) $table->foreignId('cabang_perusahaan_id')->nullable()->constrained('cabang_perusahaans')->nullOnDelete();
                if (! Schema::hasColumn('sales_transactions', 'marketing_user_id')) $table->foreignId('marketing_user_id')->nullable()->constrained('users')->nullOnDelete();
                if (! Schema::hasColumn('sales_transactions', 'payment_method')) $table->string('payment_method')->nullable();
                if (! Schema::hasColumn('sales_transactions', 'sale_price_snapshot')) $table->decimal('sale_price_snapshot', 18, 2)->nullable();
                if (! Schema::hasColumn('sales_transactions', 'party_snapshot')) $table->json('party_snapshot')->nullable();
                if (! Schema::hasColumn('sales_transactions', 'payment_snapshot')) $table->json('payment_snapshot')->nullable();
                if (! Schema::hasColumn('sales_transactions', 'status')) $table->string('status')->default('active');
                if (! Schema::hasColumn('sales_transactions', 'approved_at')) $table->timestamp('approved_at')->nullable();
                if (! Schema::hasColumn('sales_transactions', 'approved_by')) $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                if (! Schema::hasColumn('sales_transactions', 'deleted_at')) $table->softDeletes();
            });

            DB::table('sales_transactions')->orderBy('id')->get()->each(function ($transaction): void {
                DB::table('sales_transactions')->where('id', $transaction->id)->update([
                    'transaction_no' => $transaction->transaction_no ?? $transaction->transaction_number ?? 'TRX-'.str_pad((string) $transaction->id, 6, '0', STR_PAD_LEFT),
                    'cabang_perusahaan_id' => $transaction->cabang_perusahaan_id ?? $transaction->cabang_id ?? null,
                    'marketing_user_id' => $transaction->marketing_user_id ?? $transaction->marketing_id ?? null,
                    'payment_method' => $transaction->payment_method ?? $transaction->purchase_method ?? 'cash',
                    'sale_price_snapshot' => $transaction->sale_price_snapshot ?? $transaction->final_price ?? 0,
                    'party_snapshot' => $transaction->party_snapshot ?? json_encode([]),
                    'payment_snapshot' => $transaction->payment_snapshot ?? json_encode([]),
                    'status' => $transaction->status ?? $transaction->transaction_status ?? 'active',
                ]);
            });
        }
        Schema::create('cash_installment_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('contract_no')->unique();
            $table->foreignId('sales_transaction_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('cash_installment_scheme_id')->nullable()->constrained()->nullOnDelete();
            $table->json('scheme_snapshot');
            $table->decimal('contract_value', 18, 2);
            $table->string('status')->default('draft');
            $table->date('start_date');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('developer_kpr_applications', function (Blueprint $table) {
            $table->id();
            $table->string('application_no')->unique();
            $table->foreignId('sales_transaction_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('developer_kpr_product_id')->nullable()->constrained()->nullOnDelete();
            $table->json('product_snapshot');
            $table->decimal('financing_amount', 18, 2);
            $table->unsignedSmallInteger('tenor_months');
            $table->decimal('estimated_installment', 18, 2);
            $table->string('analysis_status')->default('belum_dianalisis');
            $table->string('status')->default('draft');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('payment_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_transaction_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('source');
            $table->unsignedSmallInteger('sequence');
            $table->string('type');
            $table->string('description');
            $table->date('due_date');
            $table->decimal('amount', 18, 2);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->string('status')->default('belum_dibayar');
            $table->json('calculation_snapshot')->nullable();
            $table->timestamps();
            $table->unique(['sales_transaction_id', 'source_type', 'source_id', 'sequence'], 'payment_schedule_source_sequence_unique');
        });
        Schema::create('sales_workflow_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_transaction_id')->constrained()->cascadeOnDelete();
            $table->string('process');
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
        Schema::table('sprs', function (Blueprint $table) {
            $table->foreignId('cash_installment_scheme_id')->nullable()->after('bank_credit_product_id')->constrained()->nullOnDelete();
            $table->foreignId('developer_kpr_product_id')->nullable()->after('cash_installment_scheme_id')->constrained()->nullOnDelete();
            $table->json('payment_configuration_snapshot')->nullable()->after('developer_kpr_product_id');
        });
    }

    public function down(): void
    {
        Schema::table('sprs', fn (Blueprint $table) => $table->dropConstrainedForeignId('developer_kpr_product_id'));
        Schema::table('sprs', fn (Blueprint $table) => $table->dropConstrainedForeignId('cash_installment_scheme_id'));
        Schema::table('sprs', fn (Blueprint $table) => $table->dropColumn('payment_configuration_snapshot'));
        foreach (['sales_workflow_histories', 'payment_schedules', 'developer_kpr_applications', 'cash_installment_contracts', 'sales_transactions', 'developer_kpr_products', 'cash_installment_scheme_steps', 'cash_installment_schemes'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
