<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_leads', function (Blueprint $table): void {
            $table->foreignId('possible_duplicate_lead_id')->nullable()->after('merged_by')->constrained('marketing_leads')->nullOnDelete();
            $table->text('duplicate_override_reason')->nullable()->after('possible_duplicate_lead_id');
            $table->timestamp('duplicate_checked_at')->nullable()->after('duplicate_override_reason');
            $table->foreignId('duplicate_checked_by')->nullable()->after('duplicate_checked_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('marketing_leads', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('duplicate_checked_by');
            $table->dropConstrainedForeignId('possible_duplicate_lead_id');
            $table->dropColumn(['duplicate_override_reason', 'duplicate_checked_at']);
        });
    }
};
