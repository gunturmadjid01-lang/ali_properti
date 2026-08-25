<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_nurture_sequences', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('crm_nurture_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sequence_id')->constrained('crm_nurture_sequences')->cascadeOnDelete();
            $table->unsignedInteger('step_order');
            $table->unsignedInteger('delay_minutes')->default(0);
            $table->string('title');
            $table->text('note')->nullable();
            $table->string('channel', 20)->nullable();
            $table->boolean('stop_on_contact')->default(true);
            $table->timestamps();
            $table->unique(['sequence_id', 'step_order']);
        });

        Schema::create('crm_nurture_enrollments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sequence_id')->constrained('crm_nurture_sequences')->cascadeOnDelete();
            $table->foreignId('marketing_lead_id')->constrained('marketing_leads')->cascadeOnDelete();
            $table->unsignedInteger('current_step')->default(1);
            $table->string('status', 20)->default('aktif');
            $table->timestamp('next_run_at')->nullable()->index();
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['sequence_id', 'marketing_lead_id']);
            $table->index(['status', 'next_run_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_nurture_enrollments');
        Schema::dropIfExists('crm_nurture_steps');
        Schema::dropIfExists('crm_nurture_sequences');
    }
};
