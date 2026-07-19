<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MariaDB does not roll back DDL when the second CREATE fails. These guards
        // make a retry safe after a partially completed migration.
        Schema::dropIfExists('customer_refund_items');
        Schema::dropIfExists('customer_refunds');
        Schema::create('customer_refunds', function (Blueprint $table) {
            $table->id();
            $table->string('refund_no')->unique();
            $table->foreignId('sales_resolution_request_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('sales_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('master_bank_id')->nullable()->constrained('master_banks')->nullOnDelete();
            $table->decimal('eligible_amount', 18, 2)->default(0);
            $table->decimal('penalty_amount', 18, 2)->default(0);
            $table->decimal('refund_amount', 18, 2)->default(0);
            $table->date('refund_date')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_bank')->nullable();
            $table->string('recipient_account')->nullable();
            $table->string('transfer_reference')->nullable();
            $table->string('proof_path')->nullable();
            $table->string('proof_original_name')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('draft');
            $table->string('record_status')->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('journal_id')->nullable()->constrained('journals')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'refund_date']);
        });

        Schema::create('customer_refund_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_refund_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_schedule_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->timestamps();
            $table->unique(['customer_refund_id', 'payment_schedule_id'], 'refund_item_schedule_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_refund_items');
        Schema::dropIfExists('customer_refunds');
    }
};
