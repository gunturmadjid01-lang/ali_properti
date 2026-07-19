<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_process_steps', function (Blueprint $t) {
            $t->foreignId('assigned_to')->nullable()->after('category')->constrained('users')->nullOnDelete();
            $t->string('outcome')->nullable()->after('status');
            $t->timestamp('started_at')->nullable()->after('actual_date');
        });
        Schema::create('sales_process_checklist_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sales_process_step_id')->constrained()->cascadeOnDelete();
            $t->string('item_key');
            $t->string('label');
            $t->boolean('is_required')->default(true);
            $t->boolean('is_completed')->default(false);
            $t->text('notes')->nullable();
            $t->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
            $t->unique(['sales_process_step_id', 'item_key'], 'sales_step_check_key_unique');
        });
        Schema::create('sales_process_documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sales_process_step_id')->constrained()->cascadeOnDelete();
            $t->string('document_type');
            $t->string('document_number')->nullable();
            $t->date('document_date')->nullable();
            $t->date('expires_at')->nullable();
            $t->string('file_path');
            $t->string('original_name');
            $t->string('validation_status')->default('uploaded');
            $t->text('notes')->nullable();
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('validated_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_process_documents');
        Schema::dropIfExists('sales_process_checklist_items');
        Schema::table('sales_process_steps', fn (Blueprint $t) => $t->dropConstrainedForeignId('assigned_to'));
        Schema::table('sales_process_steps', fn (Blueprint $t) => $t->dropColumn(['outcome', 'started_at']));
    }
};
