<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('office_asset_site_manpower_log');
        Schema::dropIfExists('asset_maintenance_logs');
        Schema::dropIfExists('asset_usage_logs');
        Schema::dropIfExists('asset_usage_requests');
        Schema::dropIfExists('office_assets');

        Schema::create('inventory_categories', function (Blueprint $t) { $t->id(); $t->string('name')->unique(); $t->text('description')->nullable(); $t->boolean('is_active')->default(true); $this->audit($t); });
        Schema::create('inventory_locations', function (Blueprint $t) { $t->id(); $t->string('code')->unique(); $t->string('name'); $t->string('type')->default('other'); $t->text('address')->nullable(); $t->boolean('is_active')->default(true); $this->audit($t); });
        Schema::create('inventory_items', function (Blueprint $t) {
            $t->id(); $t->string('code')->unique(); $t->string('name'); $t->foreignId('inventory_category_id')->constrained()->restrictOnDelete();
            $t->string('brand')->nullable(); $t->string('model')->nullable(); $t->string('unit')->default('Unit'); $t->string('photo')->nullable();
            $t->unsignedInteger('minimum_stock')->default(0); $t->string('inventory_type')->default('quantity');
            $t->unsignedInteger('total_stock')->default(0); $t->unsignedInteger('available_stock')->default(0); $t->unsignedInteger('borrowed_stock')->default(0); $t->unsignedInteger('damaged_stock')->default(0); $t->unsignedInteger('lost_stock')->default(0); $t->text('notes')->nullable(); $this->audit($t);
        });
        Schema::create('office_assets', function (Blueprint $t) {
            $t->id(); $t->foreignId('inventory_item_id')->constrained()->cascadeOnDelete(); $t->string('kode_aset')->unique(); $t->string('nomor_seri')->unique();
            $t->foreignId('inventory_location_id')->nullable()->constrained()->nullOnDelete(); $t->foreignId('current_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('status')->default('available'); $t->string('condition')->default('good'); $t->text('notes')->nullable(); $this->audit($t);
        });
        Schema::create('inventory_loans', function (Blueprint $t) {
            $t->id(); $t->string('transaction_no')->unique(); $t->date('date'); $t->string('borrower'); $t->string('division')->nullable(); $t->foreignId('inventory_location_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('perumahan_id')->nullable()->constrained()->nullOnDelete(); $t->string('block')->nullable(); $t->string('unit_house')->nullable(); $t->date('planned_return_date')->nullable(); $t->text('purpose'); $t->text('notes')->nullable(); $t->string('status')->default('borrowed'); $this->audit($t);
        });
        Schema::create('inventory_loan_items', function (Blueprint $t) { $t->id(); $t->foreignId('inventory_loan_id')->constrained()->cascadeOnDelete(); $t->foreignId('inventory_item_id')->constrained()->restrictOnDelete(); $t->foreignId('office_asset_id')->nullable()->constrained()->nullOnDelete(); $t->unsignedInteger('quantity')->default(1); $t->string('condition_out')->default('good'); $t->unsignedInteger('returned_quantity')->default(0); $t->timestamps(); });
        Schema::create('inventory_returns', function (Blueprint $t) { $t->id(); $t->string('return_no')->unique(); $t->foreignId('inventory_loan_id')->constrained()->restrictOnDelete(); $t->date('date'); $t->text('notes')->nullable(); $this->audit($t); });
        Schema::create('inventory_return_items', function (Blueprint $t) { $t->id(); $t->foreignId('inventory_return_id')->constrained()->cascadeOnDelete(); $t->foreignId('inventory_loan_item_id')->constrained()->restrictOnDelete(); $t->unsignedInteger('quantity'); $t->string('condition_in')->default('good'); $t->boolean('is_complete')->default(true); $t->unsignedInteger('damaged_quantity')->default(0); $t->unsignedInteger('lost_quantity')->default(0); $t->timestamps(); });
        Schema::create('inventory_transfers', function (Blueprint $t) { $t->id(); $t->string('transaction_no')->unique(); $t->date('date'); $t->foreignId('inventory_item_id')->constrained()->restrictOnDelete(); $t->foreignId('office_asset_id')->nullable()->constrained()->nullOnDelete(); $t->unsignedInteger('quantity')->default(1); $t->foreignId('from_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete(); $t->foreignId('to_location_id')->constrained('inventory_locations')->restrictOnDelete(); $t->text('reason')->nullable(); $this->audit($t); });
        Schema::create('inventory_damage_reports', function (Blueprint $t) { $t->id(); $t->foreignId('inventory_item_id')->constrained()->restrictOnDelete(); $t->foreignId('office_asset_id')->nullable()->constrained()->nullOnDelete(); $t->foreignId('inventory_location_id')->nullable()->constrained()->nullOnDelete(); $t->string('last_user')->nullable(); $t->date('date'); $t->text('damage'); $t->string('severity'); $t->string('repair_status')->default('waiting_inspection'); $t->string('photo')->nullable(); $this->audit($t); });
        Schema::create('inventory_loss_reports', function (Blueprint $t) { $t->id(); $t->foreignId('inventory_item_id')->constrained()->restrictOnDelete(); $t->foreignId('office_asset_id')->nullable()->constrained()->nullOnDelete(); $t->unsignedInteger('quantity')->default(1); $t->string('last_user')->nullable(); $t->foreignId('last_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete(); $t->date('date'); $t->text('chronology'); $t->string('responsible_person')->nullable(); $t->string('status')->default('reported'); $this->audit($t); });
        Schema::create('inventory_stock_opnames', function (Blueprint $t) { $t->id(); $t->string('opname_no')->unique(); $t->date('date'); $t->foreignId('inventory_location_id')->nullable()->constrained()->nullOnDelete(); $t->string('status')->default('draft'); $t->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamp('verified_at')->nullable(); $t->text('notes')->nullable(); $this->audit($t); });
        Schema::create('inventory_stock_opname_items', function (Blueprint $t) { $t->id(); $t->foreignId('inventory_stock_opname_id')->constrained()->cascadeOnDelete(); $t->foreignId('inventory_item_id')->constrained()->restrictOnDelete(); $t->unsignedInteger('system_quantity'); $t->unsignedInteger('physical_quantity'); $t->integer('difference'); $t->text('notes')->nullable(); $t->timestamps(); });

        Schema::create('heavy_equipment_types', function (Blueprint $t) { $t->id(); $t->string('name')->unique(); $t->text('description')->nullable(); $t->boolean('is_active')->default(true); $this->audit($t); });
        Schema::create('heavy_equipment_operators', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('phone')->nullable(); $t->string('identity_no')->nullable()->unique(); $t->string('certification')->nullable(); $t->boolean('is_active')->default(true); $this->audit($t); });
        Schema::create('heavy_equipments', function (Blueprint $t) { $t->id(); $t->string('code')->unique(); $t->string('name'); $t->foreignId('heavy_equipment_type_id')->constrained()->restrictOnDelete(); $t->string('brand')->nullable(); $t->string('model')->nullable(); $t->unsignedSmallInteger('year')->nullable(); $t->string('serial_no')->unique(); $t->string('license_plate')->nullable(); $t->decimal('current_hour_meter', 12, 2)->default(0); $t->string('ownership')->default('company'); $t->string('status')->default('active'); $t->text('notes')->nullable(); $this->audit($t); });
        Schema::create('heavy_equipment_components', function (Blueprint $t) { $t->id(); $t->string('code')->unique(); $t->string('name'); $t->foreignId('heavy_equipment_type_id')->constrained()->restrictOnDelete(); $t->foreignId('heavy_equipment_id')->nullable()->constrained()->nullOnDelete(); $t->string('component_type'); $t->string('serial_no')->nullable()->unique(); $t->string('condition')->default('good'); $t->string('status')->default('available'); $t->string('storage_location')->nullable(); $t->text('notes')->nullable(); $this->audit($t); });
        Schema::create('heavy_component_replacements', function (Blueprint $t) { $t->id(); $t->string('transaction_no')->unique(); $t->date('date'); $t->foreignId('heavy_equipment_id')->constrained()->restrictOnDelete(); $t->foreignId('old_component_id')->nullable()->constrained('heavy_equipment_components')->nullOnDelete(); $t->foreignId('new_component_id')->constrained('heavy_equipment_components')->restrictOnDelete(); $t->decimal('hour_meter', 12, 2); $t->text('reason'); $t->foreignId('operator_id')->nullable()->constrained('heavy_equipment_operators')->nullOnDelete(); $t->string('technician')->nullable(); $t->string('old_component_condition')->nullable(); $t->string('old_component_status')->default('available'); $t->text('notes')->nullable(); $this->audit($t); });
        Schema::create('heavy_equipment_usages', function (Blueprint $t) { $t->id(); $t->string('transaction_no')->unique(); $t->date('date'); $t->foreignId('heavy_equipment_id')->constrained()->restrictOnDelete(); $t->foreignId('operator_id')->constrained('heavy_equipment_operators')->restrictOnDelete(); $t->string('project')->nullable(); $t->decimal('hour_meter_start', 12, 2); $t->decimal('hour_meter_end', 12, 2)->nullable(); $t->decimal('duration_hours', 10, 2)->default(0); $t->text('description')->nullable(); $t->string('status')->default('in_use'); $this->audit($t); });
        Schema::create('heavy_equipment_maintenances', function (Blueprint $t) { $t->id(); $t->string('maintenance_no')->unique(); $t->date('date'); $t->foreignId('heavy_equipment_id')->constrained()->restrictOnDelete(); $t->string('maintenance_type'); $t->string('workshop')->nullable(); $t->decimal('cost', 18, 2)->default(0); $t->date('next_schedule')->nullable(); $t->text('notes')->nullable(); $t->string('status')->default('scheduled'); $this->audit($t); });
        Schema::create('heavy_equipment_damages', function (Blueprint $t) { $t->id(); $t->date('date'); $t->foreignId('heavy_equipment_id')->constrained()->restrictOnDelete(); $t->text('description'); $t->string('severity'); $t->string('repair_status')->default('reported'); $t->date('completed_date')->nullable(); $t->text('notes')->nullable(); $this->audit($t); });
        Schema::create('heavy_equipment_fuelings', function (Blueprint $t) { $t->id(); $t->date('date'); $t->foreignId('heavy_equipment_id')->constrained()->restrictOnDelete(); $t->string('fuel_type'); $t->decimal('liters', 12, 2); $t->decimal('price_per_liter', 18, 2); $t->decimal('total_cost', 18, 2); $t->decimal('hour_meter', 12, 2); $t->text('notes')->nullable(); $this->audit($t); });
    }

    private function audit(Blueprint $t): void
    {
        $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamps(); $t->softDeletes();
    }

    public function down(): void
    {
        foreach (['heavy_equipment_fuelings','heavy_equipment_damages','heavy_equipment_maintenances','heavy_equipment_usages','heavy_component_replacements','heavy_equipment_components','heavy_equipments','heavy_equipment_operators','heavy_equipment_types','inventory_stock_opname_items','inventory_stock_opnames','inventory_loss_reports','inventory_damage_reports','inventory_transfers','inventory_return_items','inventory_returns','inventory_loan_items','inventory_loans','office_assets','inventory_items','inventory_locations','inventory_categories'] as $table) Schema::dropIfExists($table);
    }
};
