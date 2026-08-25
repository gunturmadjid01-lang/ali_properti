<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_leads', function (Blueprint $table): void {
            $table->unsignedTinyInteger('qualification_score')->default(0)->after('qualification_status');
            $table->decimal('budget_min', 18, 2)->nullable()->after('preferred_payment_method');
            $table->decimal('budget_max', 18, 2)->nullable()->after('budget_min');
            $table->string('purchase_timeline', 30)->nullable()->after('budget_max');
            $table->string('decision_maker', 100)->nullable()->after('purchase_timeline');
            $table->string('financing_readiness', 30)->nullable()->after('decision_maker');
            $table->text('needs_summary')->nullable()->after('financing_readiness');
            $table->text('main_objection')->nullable()->after('needs_summary');
            $table->timestamp('submitted_for_verification_at')->nullable()->after('qualified_at')->index();
            $table->foreignId('submitted_for_verification_by')->nullable()->after('submitted_for_verification_at')->constrained('users')->nullOnDelete();
            $table->string('consent_status', 30)->default('unknown')->after('source_channel')->index();
            $table->json('consent_channels')->nullable()->after('consent_status');
            $table->timestamp('consent_at')->nullable()->after('consent_channels');
            $table->boolean('do_not_contact')->default(false)->after('consent_at')->index();
            $table->timestamp('recycle_at')->nullable()->after('lost_reason')->index();
            $table->unsignedSmallInteger('recycle_count')->default(0)->after('recycle_at');
            $table->foreignId('merged_into_lead_id')->nullable()->after('converted_costumer_id')->constrained('marketing_leads')->nullOnDelete();
            $table->timestamp('merged_at')->nullable()->after('merged_into_lead_id');
            $table->foreignId('merged_by')->nullable()->after('merged_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('marketing_leads', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('submitted_for_verification_by');
            $table->dropConstrainedForeignId('merged_into_lead_id');
            $table->dropConstrainedForeignId('merged_by');
            $table->dropColumn([
                'qualification_score', 'budget_min', 'budget_max', 'purchase_timeline', 'decision_maker',
                'financing_readiness', 'needs_summary', 'main_objection', 'submitted_for_verification_at',
                'consent_status', 'consent_channels', 'consent_at', 'do_not_contact', 'recycle_at',
                'recycle_count', 'merged_at',
            ]);
        });
    }
};
