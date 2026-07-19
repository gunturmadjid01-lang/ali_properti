<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_resolution_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_no')->unique();
            $table->foreignId('sales_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('spr_id')->nullable()->constrained('sprs')->nullOnDelete();
            $table->string('action'); // retry_stage, change_payment_method, close_lost
            $table->string('failed_stage')->nullable();
            $table->string('failure_category');
            $table->text('failure_reason');
            $table->string('proposed_payment_method')->nullable();
            $table->string('restart_stage')->nullable();
            $table->string('financial_treatment')->default('review_required');
            $table->text('resolution_notes')->nullable();
            $table->string('status')->default('draft');
            $table->string('record_status')->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_resolution_requests');
    }
};
