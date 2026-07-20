<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('water_billing_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('perumahan_id')->constrained('perumahans')->cascadeOnDelete();
            $table->string('period_code')->unique();
            $table->string('period_name');
            $table->date('period_start');
            $table->date('period_end');
            $table->date('due_date');
            $table->decimal('amount', 18, 2);
            $table->boolean('is_active')->default(true);
            $table->string('record_status')->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['perumahan_id', 'is_active', 'record_status'], 'water_period_housing_status_idx');
        });

        Schema::create('water_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('water_billing_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_ownership_id')->constrained()->restrictOnDelete();
            $table->foreignId('perumahan_id')->constrained('perumahans')->restrictOnDelete();
            $table->foreignId('detail_rumah_id')->constrained('detail_rumahs')->restrictOnDelete();
            $table->string('payment_no')->unique();
            $table->date('payment_date')->nullable();
            $table->decimal('amount', 18, 2);
            $table->string('payment_method')->nullable();
            $table->string('reference_no')->nullable();
            $table->string('proof_path')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('draft');
            $table->string('record_status')->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['water_billing_period_id', 'unit_ownership_id'], 'water_payment_period_owner_unique');
            $table->index(['perumahan_id', 'status', 'payment_date'], 'water_payment_housing_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('water_payments');
        Schema::dropIfExists('water_billing_periods');
    }
};
