<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_return_details', function (Blueprint $table): void {
            $table->string('condition_status', 30)->default('utuh')->after('satuan');
            $table->string('stock_disposition', 30)->default('normal')->after('condition_status');
            $table->text('condition_note')->nullable()->after('stock_disposition');
            $table->decimal('qty_normal', 18, 4)->default(0)->after('qty');
            $table->decimal('qty_quarantine', 18, 4)->default(0)->after('qty_normal');
            $table->decimal('qty_scrap', 18, 4)->default(0)->after('qty_quarantine');
            $table->decimal('qty_lost', 18, 4)->default(0)->after('qty_scrap');
        });

        Schema::create('material_condition_stocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('barang_material_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gudang_id')->constrained()->cascadeOnDelete();
            $table->string('condition_bucket', 30);
            $table->decimal('qty', 18, 4)->default(0);
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->decimal('inventory_value', 18, 2)->default(0);
            $table->timestamps();
            $table->unique(['barang_material_id', 'gudang_id', 'condition_bucket'], 'material_condition_stock_unique');
        });

        Schema::create('material_purchase_shipments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('material_purchase_id')->constrained()->cascadeOnDelete();
            $table->string('shipment_no')->unique();
            $table->string('delivery_note_no')->nullable();
            $table->string('expedition_provider')->nullable();
            $table->string('vehicle_no')->nullable();
            $table->string('driver_name')->nullable();
            $table->dateTime('shipped_at')->nullable();
            $table->dateTime('arrived_at')->nullable();
            $table->decimal('freight_cost', 18, 2)->default(0);
            $table->decimal('logistics_labor_cost', 18, 2)->default(0);
            $table->decimal('other_cost', 18, 2)->default(0);
            $table->string('status', 30)->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('material_purchase_cost_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('material_purchase_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('material_purchase_shipment_id')->nullable();
            $table->foreign('material_purchase_shipment_id', 'mp_cost_shipment_fk')->references('id')->on('material_purchase_shipments')->nullOnDelete();
            $table->string('cost_type', 30);
            $table->string('payee')->nullable();
            $table->unsignedInteger('worker_count')->nullable();
            $table->decimal('rate', 18, 2)->nullable();
            $table->decimal('amount', 18, 2);
            $table->string('proof_path')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::table('material_purchase_details', function (Blueprint $table): void {
            $table->decimal('invoice_unit_price', 18, 4)->nullable()->after('harga_satuan');
            $table->decimal('price_variance', 18, 2)->default(0)->after('invoice_unit_price');
            $table->decimal('price_variance_percent', 10, 4)->default(0)->after('price_variance');
            $table->boolean('price_variance_requires_approval')->default(false)->after('price_variance_percent');
        });

        Schema::create('material_supplier_claims', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('material_purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_purchase_detail_id')->constrained()->cascadeOnDelete();
            $table->string('claim_no')->unique();
            $table->string('claim_type', 30);
            $table->decimal('qty', 18, 4)->default(0);
            $table->decimal('amount', 18, 2)->default(0);
            $table->string('resolution', 30)->nullable();
            $table->string('status', 30)->default('diajukan');
            $table->text('notes')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('material_stock_opname_details', function (Blueprint $table): void {
            $table->decimal('unit_cost_snapshot', 18, 4)->default(0)->after('selisih');
            $table->decimal('value_before', 18, 2)->default(0)->after('unit_cost_snapshot');
            $table->decimal('value_adjustment', 18, 2)->default(0)->after('value_before');
            $table->decimal('value_after', 18, 2)->default(0)->after('value_adjustment');
        });
    }

    public function down(): void
    {
        Schema::table('material_stock_opname_details', fn (Blueprint $table) => $table->dropColumn(['unit_cost_snapshot', 'value_before', 'value_adjustment', 'value_after']));
        Schema::dropIfExists('material_supplier_claims');
        Schema::table('material_purchase_details', fn (Blueprint $table) => $table->dropColumn(['invoice_unit_price', 'price_variance', 'price_variance_percent', 'price_variance_requires_approval']));
        Schema::dropIfExists('material_purchase_cost_lines');
        Schema::dropIfExists('material_purchase_shipments');
        Schema::dropIfExists('material_condition_stocks');
        Schema::table('material_return_details', fn (Blueprint $table) => $table->dropColumn(['condition_status', 'stock_disposition', 'condition_note', 'qty_normal', 'qty_quarantine', 'qty_scrap', 'qty_lost']));
    }
};
