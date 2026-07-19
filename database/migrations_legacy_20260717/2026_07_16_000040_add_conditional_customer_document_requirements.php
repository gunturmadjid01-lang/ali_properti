<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costumers', fn (Blueprint $table) => $table->string('employment_category')->nullable()->after('pekerjaan'));
        Schema::table('bank_document_requirements', fn (Blueprint $table) => $table->foreignId('dokumen_costumer_id')->nullable()->after('bank_credit_product_id')->constrained('dokumen_costumers')->nullOnDelete());
        Schema::create('document_requirement_sets', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('application_types')->nullable();
            $table->string('status')->default('aktif');
            $table->string('record_status')->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('document_requirement_set_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_requirement_set_id');
            $table->foreign('document_requirement_set_id', 'document_set_item_set_fk')
                ->references('id')->on('document_requirement_sets')->cascadeOnDelete();
            $table->foreignId('dokumen_costumer_id')->constrained('dokumen_costumers')->restrictOnDelete();
            $table->json('employment_categories')->nullable();
            $table->json('marital_statuses')->nullable();
            $table->string('party_scope')->default('customer');
            $table->boolean('is_required')->default(true);
            $table->unsignedSmallInteger('validity_days')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['document_requirement_set_id', 'dokumen_costumer_id', 'party_scope'], 'document_set_item_unique');
        });
        foreach (['banks' => 'bank_kredits', 'products' => 'bank_credit_products', 'housings' => 'perumahans', 'companies' => 'cabang_perusahaans', 'partnerships' => 'bank_housing_partnerships'] as $suffix => $target) {
            Schema::create("document_requirement_set_{$suffix}", function (Blueprint $table) use ($suffix, $target) {
                $table->foreignId('document_requirement_set_id');
                $table->foreign('document_requirement_set_id', "doc_set_{$suffix}_set_fk")
                    ->references('id')->on('document_requirement_sets')->cascadeOnDelete();
                $foreign = rtrim($target, 's').'_id';
                $foreign = match ($suffix) {
                    'companies' => 'cabang_perusahaan_id','housings' => 'perumahan_id','partnerships' => 'bank_housing_partnership_id','products' => 'bank_credit_product_id',default => 'bank_kredit_id'
                };
                $table->foreignId($foreign);
                $table->foreign($foreign, "doc_set_{$suffix}_target_fk")
                    ->references('id')->on($target)->cascadeOnDelete();
                $table->primary(['document_requirement_set_id', $foreign], "doc_set_{$suffix}_primary");
            });
        }
    }

    public function down(): void
    {
        foreach (['partnerships', 'companies', 'housings', 'products', 'banks'] as $suffix) {
            Schema::dropIfExists("document_requirement_set_{$suffix}");
        }
        Schema::dropIfExists('document_requirement_set_items');
        Schema::dropIfExists('document_requirement_sets');
        Schema::table('bank_document_requirements', fn (Blueprint $table) => $table->dropConstrainedForeignId('dokumen_costumer_id'));
        Schema::table('costumers', fn (Blueprint $table) => $table->dropColumn('employment_category'));
    }
};
