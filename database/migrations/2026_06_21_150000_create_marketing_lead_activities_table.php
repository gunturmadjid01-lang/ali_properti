<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_lead_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('costumer_id')->constrained('costumers')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status_from')->nullable();
            $table->string('status_to');
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->timestamp('activity_at')->useCurrent();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['activity_at', 'status_to']);
            $table->index(['costumer_id', 'activity_at']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_lead_activities');
    }
};
