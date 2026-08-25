<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costumers', function (Blueprint $table): void {
            $table->string('lead_ownership_type', 20)->default('marketing')->after('marketing_campaign_id');
            $table->string('lead_source_channel', 50)->nullable()->after('lead_ownership_type');
            $table->string('lead_verification_status', 30)->default('pending')->after('lead_source_channel');
            $table->text('lead_verification_note')->nullable()->after('lead_verification_status');
            $table->foreignId('lead_verified_by')->nullable()->after('lead_verification_note')->constrained('users')->nullOnDelete();
            $table->timestamp('lead_verified_at')->nullable()->after('lead_verified_by');
            $table->string('assignment_status', 30)->default('unassigned')->after('lead_verified_at');
            $table->foreignId('admin_sales_id')->nullable()->after('assignment_status')->constrained('users')->nullOnDelete();
            $table->index(['lead_ownership_type', 'lead_verification_status'], 'costumers_lead_owner_verification_index');
            $table->index(['admin_sales_id', 'assignment_status'], 'costumers_admin_sales_assignment_index');
        });

        foreach (['costumer_follow_ups', 'marketing_visits'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('admin_review_status', 30)->default('pending')->after('updated_at');
                $table->text('admin_review_note')->nullable()->after('admin_review_status');
                $table->foreignId('admin_reviewed_by')->nullable()->after('admin_review_note')->constrained('users')->nullOnDelete();
                $table->timestamp('admin_reviewed_at')->nullable()->after('admin_reviewed_by');
                $table->index(['admin_review_status', 'admin_reviewed_at']);
            });
        }

        Schema::create('sales_work_items', function (Blueprint $table): void {
            $table->id();
            $table->string('work_no')->unique();
            $table->string('category', 50);
            $table->string('title');
            $table->text('description')->nullable();
            $table->nullableMorphs('subject');
            $table->foreignId('costumer_id')->nullable()->constrained('costumers')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('priority', 20)->default('medium');
            $table->string('status', 30)->default('open');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['assigned_to', 'status', 'due_at']);
            $table->index(['category', 'priority']);
        });

        Schema::create('sales_activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->morphs('subject');
            $table->string('event', 60);
            $table->string('old_status', 50)->nullable();
            $table->string('new_status', 50)->nullable();
            $table->text('reason')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('role_name')->nullable();
            $table->string('source', 40)->default('web');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            $table->index(['event', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_activity_logs');
        Schema::dropIfExists('sales_work_items');
        foreach (['marketing_visits', 'costumer_follow_ups'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropForeign(['admin_reviewed_by']);
                $table->dropIndex(['admin_review_status', 'admin_reviewed_at']);
                $table->dropColumn(['admin_review_status', 'admin_review_note', 'admin_reviewed_by', 'admin_reviewed_at']);
            });
        }
        Schema::table('costumers', function (Blueprint $table): void {
            $table->dropForeign(['lead_verified_by']);
            $table->dropForeign(['admin_sales_id']);
            $table->dropIndex('costumers_lead_owner_verification_index');
            $table->dropIndex('costumers_admin_sales_assignment_index');
            $table->dropColumn(['lead_ownership_type', 'lead_source_channel', 'lead_verification_status', 'lead_verification_note', 'lead_verified_by', 'lead_verified_at', 'assignment_status', 'admin_sales_id']);
        });
    }
};
