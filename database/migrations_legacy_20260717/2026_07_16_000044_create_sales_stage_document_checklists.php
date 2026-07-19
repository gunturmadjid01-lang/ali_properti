<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_stage_document_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_process_step_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_requirement_set_item_id');
            $table->foreign('document_requirement_set_item_id', 'stage_document_check_requirement_fk')
                ->references('id')->on('document_requirement_set_items')->cascadeOnDelete();
            $table->boolean('is_complete')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
            $table->unique(['sales_process_step_id', 'document_requirement_set_item_id'], 'stage_requirement_check_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_stage_document_checklists');
    }
};
