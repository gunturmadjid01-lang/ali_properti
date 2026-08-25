<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costumer_follow_ups', function (Blueprint $table): void {
            $table->foreignId('marketing_lead_id')->nullable()->after('id')->constrained('marketing_leads')->nullOnDelete();
            $table->unsignedBigInteger('costumer_id')->nullable()->change();
        });
        Schema::table('marketing_lead_assignments', function (Blueprint $table): void {
            $table->foreignId('marketing_lead_id')->nullable()->after('id')->constrained('marketing_leads')->cascadeOnDelete();
            $table->unsignedBigInteger('costumer_id')->nullable()->change();
        });
        Schema::table('sales_work_items', function (Blueprint $table): void {
            $table->foreignId('marketing_lead_id')->nullable()->after('costumer_id')->constrained('marketing_leads')->nullOnDelete();
        });
        Schema::table('sales_lead_intake_rows', function (Blueprint $table): void {
            $table->foreignId('marketing_lead_id')->nullable()->after('costumer_id')->constrained('marketing_leads')->nullOnDelete();
            $table->foreignId('duplicate_marketing_lead_id')->nullable()->after('duplicate_costumer_id')->constrained('marketing_leads')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales_lead_intake_rows', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('duplicate_marketing_lead_id');
            $table->dropConstrainedForeignId('marketing_lead_id');
        });
        Schema::table('sales_work_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('marketing_lead_id'));
        Schema::table('marketing_lead_assignments', fn (Blueprint $table) => $table->dropConstrainedForeignId('marketing_lead_id'));
        Schema::table('costumer_follow_ups', fn (Blueprint $table) => $table->dropConstrainedForeignId('marketing_lead_id'));
    }
};
