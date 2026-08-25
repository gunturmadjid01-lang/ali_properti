<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_lead_import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_no')->unique();
            $table->string('source_type', 30);
            $table->string('original_filename')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->unsignedInteger('duplicate_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('sales_lead_intake_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->nullable()->constrained('sales_lead_import_batches')->nullOnDelete();
            $table->string('idempotency_key')->nullable()->unique();
            $table->string('source_type', 30);
            $table->string('status', 30)->default('pending');
            $table->string('name')->nullable();
            $table->string('phone', 30)->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->json('payload');
            $table->text('validation_note')->nullable();
            $table->foreignId('duplicate_costumer_id')->nullable()->constrained('costumers')->nullOnDelete();
            $table->foreignId('costumer_id')->nullable()->constrained('costumers')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_lead_intake_rows');
        Schema::dropIfExists('sales_lead_import_batches');
    }
};
