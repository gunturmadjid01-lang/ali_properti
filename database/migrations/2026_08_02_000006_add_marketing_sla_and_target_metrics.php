<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costumers', function (Blueprint $table): void {
            $table->timestamp('lead_received_at')->nullable()->after('assigned_at');
            $table->timestamp('first_response_due_at')->nullable()->after('lead_received_at');
            $table->string('lead_priority', 20)->default('normal')->after('first_response_due_at');
            $table->string('interest_level', 20)->nullable()->after('lead_priority');
            $table->decimal('budget_min', 18, 2)->nullable()->after('interest_level');
            $table->decimal('budget_max', 18, 2)->nullable()->after('budget_min');
            $table->string('preferred_payment_method', 30)->nullable()->after('budget_max');
            $table->text('cancellation_reason')->nullable()->after('lost_reason');
            $table->index(['assigned_marketing_id', 'first_response_due_at']);
            $table->index(['lead_priority', 'status_lead']);
        });

        Schema::table('costumer_follow_ups', function (Blueprint $table): void {
            $table->dateTime('followed_up_at')->nullable()->after('tanggal_follow_up');
            $table->string('result_code', 50)->nullable()->after('progress_kemampuan');
            $table->string('interest_level', 20)->nullable()->after('result_code');
            $table->text('obstacle')->nullable()->after('catatan');
            $table->text('next_action')->nullable()->after('obstacle');
            $table->string('attachment_path')->nullable()->after('next_action');
            $table->index(['user_id', 'followed_up_at']);
            $table->index(['result_code', 'interest_level']);
        });

        Schema::table('marketing_targets', function (Blueprint $table): void {
            $table->unsignedInteger('target_follow_up')->default(0)->after('target_lead');
            $table->unsignedInteger('target_visit')->default(0)->after('target_follow_up');
            $table->unsignedInteger('target_reservation')->default(0)->after('target_survey');
        });

        DB::table('costumers')->whereNull('lead_received_at')->update(['lead_received_at' => DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)')]);
        DB::table('costumers')->whereNull('first_response_due_at')->update(['first_response_due_at' => DB::raw('DATE_ADD(COALESCE(lead_received_at, created_at, CURRENT_TIMESTAMP), INTERVAL 2 HOUR)')]);
    }

    public function down(): void
    {
        Schema::table('marketing_targets', fn (Blueprint $table) => $table->dropColumn(['target_follow_up', 'target_visit', 'target_reservation']));
        Schema::table('costumer_follow_ups', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'followed_up_at']);
            $table->dropIndex(['result_code', 'interest_level']);
            $table->dropColumn(['followed_up_at', 'result_code', 'interest_level', 'obstacle', 'next_action', 'attachment_path']);
        });
        Schema::table('costumers', function (Blueprint $table): void {
            $table->dropIndex(['assigned_marketing_id', 'first_response_due_at']);
            $table->dropIndex(['lead_priority', 'status_lead']);
            $table->dropColumn(['lead_received_at', 'first_response_due_at', 'lead_priority', 'interest_level', 'budget_min', 'budget_max', 'preferred_payment_method', 'cancellation_reason']);
        });
    }
};
