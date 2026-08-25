<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costumers', function (Blueprint $table): void {
            $table->foreignId('assigned_marketing_id')->nullable()->after('perumahan_id')->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable()->after('assigned_marketing_id');
            $table->timestamp('first_contacted_at')->nullable()->after('assigned_at');
            $table->timestamp('last_activity_at')->nullable()->after('first_contacted_at');
            $table->timestamp('next_action_at')->nullable()->after('last_activity_at');
            $table->string('lost_reason')->nullable()->after('next_action_at');
            $table->index(['assigned_marketing_id', 'status_lead']);
            $table->index(['last_activity_at', 'next_action_at']);
        });

        Schema::create('marketing_lead_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('costumer_id')->constrained('costumers')->cascadeOnDelete();
            $table->foreignId('from_marketing_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_marketing_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamps();
            $table->index(['costumer_id', 'assigned_at']);
        });

        Schema::create('marketing_visits', function (Blueprint $table): void {
            $table->id();
            $table->string('visit_no')->unique();
            $table->foreignId('costumer_id')->constrained('costumers')->cascadeOnDelete();
            $table->foreignId('marketing_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('perumahan_id')->nullable()->constrained('perumahans')->nullOnDelete();
            $table->dateTime('planned_at');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->string('visit_type')->default('customer_location');
            $table->string('status')->default('planned');
            $table->string('location')->nullable();
            $table->text('objective');
            $table->text('customer_response')->nullable();
            $table->text('objections')->nullable();
            $table->text('result')->nullable();
            $table->string('interest_level')->nullable();
            $table->text('next_action')->nullable();
            $table->dateTime('next_action_at')->nullable();
            $table->string('evidence_path')->nullable();
            $table->string('record_status')->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['marketing_id', 'planned_at', 'status']);
        });

        Schema::create('marketing_action_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('action_no')->unique();
            $table->foreignId('costumer_id')->nullable()->constrained('costumers')->cascadeOnDelete();
            $table->foreignId('marketing_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('perumahan_id')->nullable()->constrained('perumahans')->nullOnDelete();
            $table->string('title');
            $table->text('objective');
            $table->text('expected_result')->nullable();
            $table->text('actual_result')->nullable();
            $table->string('priority')->default('medium');
            $table->string('status')->default('planned');
            $table->dateTime('start_at');
            $table->dateTime('due_at');
            $table->dateTime('completed_at')->nullable();
            $table->text('blocker')->nullable();
            $table->text('supervisor_note')->nullable();
            $table->string('record_status')->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['marketing_id', 'status', 'due_at']);
        });

        Schema::create('customer_document_checklists', function (Blueprint $table): void {
            $table->id();
            $table->string('checklist_no')->unique();
            $table->foreignId('costumer_id')->constrained('costumers')->cascadeOnDelete();
            $table->foreignId('perumahan_id')->nullable()->constrained('perumahans')->nullOnDelete();
            $table->string('process_stage')->default('qualification');
            $table->json('items');
            $table->unsignedTinyInteger('completion_percentage')->default(0);
            $table->string('validation_status')->default('incomplete');
            $table->text('notes')->nullable();
            $table->string('record_status')->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['costumer_id', 'process_stage'], 'customer_document_checklists_customer_stage_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_document_checklists');
        Schema::dropIfExists('marketing_action_plans');
        Schema::dropIfExists('marketing_visits');
        Schema::dropIfExists('marketing_lead_assignments');

        Schema::table('costumers', function (Blueprint $table): void {
            $table->dropForeign(['assigned_marketing_id']);
            $table->dropColumn([
                'assigned_marketing_id', 'assigned_at', 'first_contacted_at', 'last_activity_at',
                'next_action_at', 'lost_reason',
            ]);
        });
    }
};
