<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_supplier_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('material_purchase_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invoice_no')->nullable();
            $table->date('invoice_date')->nullable();
            $table->decimal('gross_amount', 18, 2)->default(0);
            $table->decimal('accepted_amount', 18, 2)->default(0);
            $table->decimal('claim_amount', 18, 2)->default(0);
            $table->decimal('payable_amount', 18, 2)->default(0);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->decimal('outstanding_amount', 18, 2)->default(0);
            $table->string('status', 30)->default('draft');
            $table->timestamps();
        });

        Schema::table('material_supplier_claims', function (Blueprint $table): void {
            $table->string('record_status', 20)->default('draft')->after('status');
            $table->dateTime('locked_at')->nullable()->after('record_status');
            $table->foreignId('locked_by')->nullable()->after('locked_at')->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->after('resolved_at')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });

        Schema::table('material_stock_lots', function (Blueprint $table): void {
            $table->unsignedBigInteger('material_purchase_id')->nullable()->change();
            $table->unsignedBigInteger('material_purchase_detail_id')->nullable()->change();
            $table->string('source_type')->nullable()->after('material_purchase_detail_id');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
        });

        Schema::create('material_stock_lot_allocations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('transaksi_logistik_detail_id');
            $table->unsignedBigInteger('material_stock_lot_id');
            $table->foreign('transaksi_logistik_detail_id', 'msla_logistik_detail_fk')->references('id')->on('transaksi_logistik_details')->cascadeOnDelete();
            $table->foreign('material_stock_lot_id', 'msla_stock_lot_fk')->references('id')->on('material_stock_lots')->cascadeOnDelete();
            $table->decimal('qty', 18, 4);
            $table->decimal('unit_cost', 18, 4);
            $table->decimal('amount', 18, 2);
            $table->timestamps();
        });

        Schema::table('material_opening_balances', function (Blueprint $table): void {
            $table->dateTime('stock_posted_at')->nullable()->after('locked_by');
        });
        Schema::table('material_stock_opnames', function (Blueprint $table): void {
            $table->dateTime('stock_posted_at')->nullable()->after('locked_by');
        });
        DB::table('material_opening_balances')->where('qty', '>', 0)->update(['stock_posted_at' => DB::raw('COALESCE(locked_at, created_at)')]);
        DB::table('material_stock_opnames')->update(['stock_posted_at' => DB::raw('COALESCE(locked_at, created_at)')]);

        DB::table('chart_of_accounts')->updateOrInsert(
            ['kode_akun' => '1-1200'],
            ['nama_akun' => 'Piutang Supplier', 'kategori' => 'aset', 'posisi_normal' => 'debit', 'status' => 'aktif', 'is_system' => true, 'created_at' => now(), 'updated_at' => now()],
        );
    }

    public function down(): void
    {
        Schema::table('material_stock_opnames', fn (Blueprint $table) => $table->dropColumn('stock_posted_at'));
        Schema::table('material_opening_balances', fn (Blueprint $table) => $table->dropColumn('stock_posted_at'));
        Schema::dropIfExists('material_stock_lot_allocations');
        Schema::table('material_stock_lots', fn (Blueprint $table) => $table->dropColumn(['source_type', 'source_id']));
        Schema::table('material_supplier_claims', fn (Blueprint $table) => $table->dropConstrainedForeignId('updated_by'));
        Schema::table('material_supplier_claims', fn (Blueprint $table) => $table->dropConstrainedForeignId('resolved_by'));
        Schema::table('material_supplier_claims', fn (Blueprint $table) => $table->dropConstrainedForeignId('locked_by'));
        Schema::table('material_supplier_claims', fn (Blueprint $table) => $table->dropColumn(['record_status', 'locked_at']));
        Schema::dropIfExists('material_supplier_invoices');
    }
};
