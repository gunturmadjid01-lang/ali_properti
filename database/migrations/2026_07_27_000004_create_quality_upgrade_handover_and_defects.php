<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quality_upgrade_contracts', function (Blueprint $table): void {
            $table->date('warranty_end_date')->nullable()->after('handed_over_at');
        });

        Schema::create('quality_upgrade_handovers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quality_upgrade_contract_id')->unique()->constrained()->cascadeOnDelete();
            $table->date('handover_date');
            $table->decimal('final_progress_percent', 5, 2)->default(100);
            $table->json('checklist');
            $table->text('notes')->nullable();
            $table->string('customer_evidence_path')->nullable();
            $table->string('supervisor_evidence_path')->nullable();
            $table->string('status', 30)->default('draft');
            $table->string('record_status', 20)->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('quality_upgrade_defects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quality_upgrade_contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quality_upgrade_contract_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('defect_no')->unique();
            $table->date('reported_date');
            $table->string('severity', 20);
            $table->text('description');
            $table->string('evidence_path')->nullable();
            $table->date('target_date')->nullable();
            $table->string('status', 30)->default('open');
            $table->text('resolution_notes')->nullable();
            $table->string('resolution_evidence_path')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['quality_upgrade_contract_id', 'status'], 'qu_defect_contract_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_upgrade_defects');
        Schema::dropIfExists('quality_upgrade_handovers');
        Schema::table('quality_upgrade_contracts', fn (Blueprint $table) => $table->dropColumn('warranty_end_date'));
    }
};
