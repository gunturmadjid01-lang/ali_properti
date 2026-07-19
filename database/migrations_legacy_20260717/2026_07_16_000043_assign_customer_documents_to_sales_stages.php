<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_requirement_set_items', function (Blueprint $table) {
            $table->string('process_stage_code')->nullable()->after('party_scope')->index();
        });
        Schema::create('sales_process_customer_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_process_step_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_requirement_set_item_id')->nullable();
            $table->foreign('document_requirement_set_item_id', 'stage_customer_document_requirement_fk')
                ->references('id')->on('document_requirement_set_items')->nullOnDelete();
            $table->string('validation_status')->default('selected');
            $table->text('validation_notes')->nullable();
            $table->foreignId('selected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['sales_process_step_id', 'customer_document_id'], 'stage_customer_document_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_process_customer_documents');
        Schema::table('document_requirement_set_items', fn (Blueprint $table) => $table->dropColumn('process_stage_code'));
    }
};
