<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_installment_schemes', function (Blueprint $table) {
            $table->string('dp_type')->default('nominal')->after('minimum_dp');
            $table->string('booking_fee_deducts')->default('down_payment')->after('minimum_booking_fee');
            $table->string('payment_model')->default('equal_monthly')->after('maximum_tenor_months');
            $table->json('unit_types')->nullable();
            $table->json('schedule_config')->nullable();
            $table->json('penalty_config')->nullable();
            $table->json('handover_config')->nullable();
            $table->json('document_requirements')->nullable();
        });
        Schema::table('developer_kpr_products', function (Blueprint $table) {
            $table->string('dp_type')->default('nominal')->after('minimum_dp');
            $table->string('financing_type')->default('nominal')->after('maximum_financing');
            $table->string('financing_basis')->default('final_price')->after('financing_type');
            $table->string('tenor_mode')->default('range')->after('maximum_tenor_months');
            $table->unsignedSmallInteger('tenor_increment')->default(12)->after('tenor_mode');
            $table->string('margin_scope')->default('all')->after('margin_method');
            $table->json('unit_types')->nullable();
            $table->json('margin_tiers')->nullable();
            $table->json('fees')->nullable();
            $table->json('schedule_config')->nullable();
            $table->json('penalty_config')->nullable();
            $table->json('eligibility_config')->nullable();
            $table->json('document_requirements')->nullable();
            $table->json('handover_config')->nullable();
            $table->json('advanced_config')->nullable();
        });
        Schema::create('cash_installment_scheme_housing', function (Blueprint $table) {
            $table->foreignId('cash_installment_scheme_id');
            $table->foreign('cash_installment_scheme_id', 'cash_scheme_housing_scheme_fk')
                ->references('id')->on('cash_installment_schemes')->cascadeOnDelete();
            $table->foreignId('perumahan_id')->constrained()->cascadeOnDelete();
            $table->primary(['cash_installment_scheme_id', 'perumahan_id'], 'cash_scheme_housing_primary');
        });
        Schema::create('developer_kpr_product_housing', function (Blueprint $table) {
            $table->foreignId('developer_kpr_product_id');
            $table->foreign('developer_kpr_product_id', 'developer_product_housing_product_fk')
                ->references('id')->on('developer_kpr_products')->cascadeOnDelete();
            $table->foreignId('perumahan_id')->constrained()->cascadeOnDelete();
            $table->primary(['developer_kpr_product_id', 'perumahan_id'], 'developer_product_housing_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('developer_kpr_product_housing');
        Schema::dropIfExists('cash_installment_scheme_housing');
        Schema::table('developer_kpr_products', fn (Blueprint $table) => $table->dropColumn(['dp_type', 'financing_type', 'financing_basis', 'tenor_mode', 'tenor_increment', 'margin_scope', 'unit_types', 'margin_tiers', 'fees', 'schedule_config', 'penalty_config', 'eligibility_config', 'document_requirements', 'handover_config', 'advanced_config']));
        Schema::table('cash_installment_schemes', fn (Blueprint $table) => $table->dropColumn(['dp_type', 'booking_fee_deducts', 'payment_model', 'unit_types', 'schedule_config', 'penalty_config', 'handover_config', 'document_requirements']));
    }
};
