<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_kredits', function (Blueprint $table) {
            $table->string('jenis_bank')->default('konvensional');
            $table->text('alamat_pusat')->nullable();
            $table->string('website')->nullable();
            $table->string('logo')->nullable();
            $table->string('nomor_telepon')->nullable();
            $table->string('email')->nullable();
            $table->text('catatan')->nullable();
        });

        Schema::create('bank_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_kredit_id')->constrained('bank_kredits')->restrictOnDelete();
            $table->string('branch_code');
            $table->string('branch_name');
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('pic_name')->nullable();
            $table->string('pic_position')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('status')->default('aktif');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['bank_kredit_id', 'branch_code']);
        });

        Schema::create('bank_credit_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_kredit_id')->constrained('bank_kredits')->restrictOnDelete();
            $table->foreignId('bank_branch_id')->nullable()->constrained('bank_branches')->nullOnDelete();
            $table->string('product_code')->unique();
            $table->string('product_name');
            $table->string('product_type');
            $table->string('subsidy_type')->default('non_subsidi');
            $table->string('scheme_type')->default('konvensional');
            $table->decimal('minimum_ceiling', 18, 2)->default(0);
            $table->decimal('maximum_ceiling', 18, 2)->default(0);
            $table->decimal('minimum_down_payment', 18, 2)->default(0);
            $table->unsignedSmallInteger('maximum_tenor_months')->default(1);
            $table->decimal('indicative_interest_margin', 8, 4)->default(0);
            $table->decimal('provision_fee', 18, 2)->default(0);
            $table->decimal('administration_fee', 18, 2)->default(0);
            $table->decimal('appraisal_fee', 18, 2)->default(0);
            $table->decimal('insurance_fee', 18, 2)->default(0);
            $table->decimal('notary_fee', 18, 2)->default(0);
            $table->string('disbursement_method');
            $table->unsignedSmallInteger('estimated_sla_days')->nullable();
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->unsignedInteger('current_version')->default(1);
            $table->string('status')->default('aktif');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('bank_credit_product_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_credit_product_id')->constrained('bank_credit_products')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->json('terms_snapshot');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['bank_credit_product_id', 'version_number'], 'bank_credit_product_version_unique');
        });

        Schema::create('bank_housing_partnerships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_kredit_id')->constrained('bank_kredits')->restrictOnDelete();
            $table->foreignId('bank_branch_id')->nullable()->constrained('bank_branches')->nullOnDelete();
            $table->foreignId('perumahan_id')->constrained('perumahans')->restrictOnDelete();
            $table->string('agreement_number');
            $table->string('agreement_name');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->unsignedInteger('current_version')->default(1);
            $table->string('status')->default('aktif');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['bank_kredit_id', 'perumahan_id', 'agreement_number'], 'bank_housing_agreement_unique');
        });

        Schema::create('bank_housing_partnership_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_housing_partnership_id');
            $table->foreign('bank_housing_partnership_id', 'bank_housing_version_partnership_fk')
                ->references('id')->on('bank_housing_partnerships')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->json('agreement_snapshot');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['bank_housing_partnership_id', 'version_number'], 'bank_housing_partnership_version_unique');
        });

        Schema::create('bank_document_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_kredit_id')->constrained('bank_kredits')->restrictOnDelete();
            $table->foreignId('bank_credit_product_id')->nullable()->constrained('bank_credit_products')->cascadeOnDelete();
            $table->string('document_code');
            $table->string('document_name');
            $table->string('requirement_for')->default('customer');
            $table->boolean('is_required')->default(true);
            $table->unsignedSmallInteger('validity_days')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status')->default('aktif');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['bank_kredit_id', 'bank_credit_product_id', 'document_code'], 'bank_doc_requirement_unique');
        });

        Schema::table('kpr_submissions', function (Blueprint $table) {
            $table->foreignId('bank_branch_id')->nullable()->constrained('bank_branches')->nullOnDelete();
            $table->foreignId('bank_credit_product_id')->nullable()->constrained('bank_credit_products')->nullOnDelete();
            $table->foreignId('bank_credit_product_version_id')->nullable()->constrained('bank_credit_product_versions')->nullOnDelete();
            $table->json('bank_product_snapshot')->nullable();
        });

        Schema::table('sprs', function (Blueprint $table) {
            $table->foreignId('bank_credit_product_id')->nullable()->constrained('bank_credit_products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sprs', fn (Blueprint $table) => $table->dropConstrainedForeignId('bank_credit_product_id'));
        Schema::table('kpr_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_credit_product_version_id');
            $table->dropConstrainedForeignId('bank_credit_product_id');
            $table->dropConstrainedForeignId('bank_branch_id');
            $table->dropColumn('bank_product_snapshot');
        });
        Schema::dropIfExists('bank_document_requirements');
        Schema::dropIfExists('bank_housing_partnership_versions');
        Schema::dropIfExists('bank_housing_partnerships');
        Schema::dropIfExists('bank_credit_product_versions');
        Schema::dropIfExists('bank_credit_products');
        Schema::dropIfExists('bank_branches');
        Schema::table('bank_kredits', fn (Blueprint $table) => $table->dropColumn(['jenis_bank', 'alamat_pusat', 'website', 'logo', 'nomor_telepon', 'email', 'catatan']));
    }
};
