<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quality_upgrade_catalogs', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('specification')->nullable();
            $table->string('unit', 30);
            $table->decimal('standard_price', 18, 2)->default(0);
            $table->decimal('estimated_material_cost', 18, 2)->default(0);
            $table->decimal('estimated_labor_cost', 18, 2)->default(0);
            $table->decimal('estimated_other_cost', 18, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        DB::table('quality_upgrade_catalogs')->insert([
            ['code' => 'PM-KANOPI', 'name' => 'Kanopi', 'specification' => 'Rangka dan penutup sesuai hasil ukur lapangan', 'unit' => 'm2', 'standard_price' => 0, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'PM-DAPUR', 'name' => 'Dapur', 'specification' => 'Pekerjaan meja, finishing, instalasi air dan listrik sesuai kontrak', 'unit' => 'paket', 'standard_price' => 0, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'PM-PAGAR', 'name' => 'Pagar', 'specification' => 'Pagar dan pintu pagar sesuai ukuran lapangan', 'unit' => 'm1', 'standard_price' => 0, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'PM-CARPORT', 'name' => 'Carport', 'specification' => 'Pekerjaan lantai dan drainase carport', 'unit' => 'm2', 'standard_price' => 0, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::table('quality_upgrade_contracts', function (Blueprint $table): void {
            $table->decimal('progress_percent', 5, 2)->default(0)->after('business_status');
            $table->decimal('actual_material_cost', 18, 2)->default(0)->after('progress_percent');
            $table->decimal('actual_labor_cost', 18, 2)->default(0)->after('actual_material_cost');
            $table->decimal('actual_other_cost', 18, 2)->default(0)->after('actual_labor_cost');
            $table->timestamp('started_at')->nullable()->after('actual_other_cost');
            $table->timestamp('completed_at')->nullable()->after('started_at');
            $table->timestamp('handed_over_at')->nullable()->after('completed_at');
            $table->timestamp('cancelled_at')->nullable()->after('handed_over_at');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable()->after('cancelled_by');
            $table->unsignedInteger('document_version')->default(1)->after('cancellation_reason');
        });

        Schema::table('quality_upgrade_contract_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('quality_upgrade_catalog_id')->nullable()->after('quality_upgrade_contract_id');
            $table->foreign('quality_upgrade_catalog_id', 'qu_item_catalog_fk')->references('id')->on('quality_upgrade_catalogs')->nullOnDelete();
            $table->decimal('estimated_material_cost', 18, 2)->default(0)->after('total');
            $table->decimal('estimated_labor_cost', 18, 2)->default(0)->after('estimated_material_cost');
            $table->decimal('estimated_other_cost', 18, 2)->default(0)->after('estimated_labor_cost');
        });

        Schema::create('quality_upgrade_progresses', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('quality_upgrade_contract_id');
            $table->foreign('quality_upgrade_contract_id', 'qu_progress_contract_fk')->references('id')->on('quality_upgrade_contracts')->cascadeOnDelete();
            $table->unsignedBigInteger('quality_upgrade_contract_item_id')->nullable();
            $table->foreign('quality_upgrade_contract_item_id', 'qu_progress_item_fk')->references('id')->on('quality_upgrade_contract_items')->nullOnDelete();
            $table->date('report_date');
            $table->decimal('progress_percent', 5, 2);
            $table->string('work_status', 30);
            $table->decimal('material_cost', 18, 2)->default(0);
            $table->decimal('labor_cost', 18, 2)->default(0);
            $table->decimal('other_cost', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->text('inspection_notes')->nullable();
            $table->string('evidence_path')->nullable();
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['quality_upgrade_contract_id', 'report_date'], 'qu_progress_contract_date_idx');
        });

        Schema::create('quality_upgrade_addenda', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quality_upgrade_contract_id')->constrained()->cascadeOnDelete();
            $table->string('addendum_no')->unique();
            $table->date('addendum_date');
            $table->text('reason');
            $table->decimal('value_change', 18, 2)->default(0);
            $table->date('finish_date_change')->nullable();
            $table->json('change_snapshot');
            $table->string('record_status', 20)->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('material_usages', function (Blueprint $table): void {
            $table->unsignedBigInteger('quality_upgrade_contract_id')->nullable()->after('material_request_id');
            $table->foreign('quality_upgrade_contract_id', 'material_usage_qu_contract_fk')->references('id')->on('quality_upgrade_contracts')->nullOnDelete();
            $table->unsignedBigInteger('quality_upgrade_contract_item_id')->nullable()->after('quality_upgrade_contract_id');
            $table->foreign('quality_upgrade_contract_item_id', 'material_usage_qu_item_fk')->references('id')->on('quality_upgrade_contract_items')->nullOnDelete();
            $table->index(['quality_upgrade_contract_id', 'record_status'], 'material_usage_upgrade_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('material_usages', function (Blueprint $table): void {
            $table->dropIndex('material_usage_upgrade_status_idx');
            $table->dropForeign('material_usage_qu_item_fk');
            $table->dropForeign('material_usage_qu_contract_fk');
            $table->dropColumn(['quality_upgrade_contract_item_id', 'quality_upgrade_contract_id']);
        });
        Schema::dropIfExists('quality_upgrade_addenda');
        Schema::dropIfExists('quality_upgrade_progresses');
        Schema::table('quality_upgrade_contract_items', function (Blueprint $table): void {
            $table->dropForeign('qu_item_catalog_fk');
            $table->dropColumn('quality_upgrade_catalog_id');
            $table->dropColumn(['estimated_material_cost', 'estimated_labor_cost', 'estimated_other_cost']);
        });
        Schema::table('quality_upgrade_contracts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['progress_percent', 'actual_material_cost', 'actual_labor_cost', 'actual_other_cost', 'started_at', 'completed_at', 'handed_over_at', 'cancelled_at', 'cancellation_reason', 'document_version']);
        });
        Schema::dropIfExists('quality_upgrade_catalogs');
    }
};
