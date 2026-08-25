<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_reminders', fn (Blueprint $table) => $table->foreignId('marketing_lead_id')->nullable()->after('costumer_id')->constrained('marketing_leads')->nullOnDelete());
    }

    public function down(): void
    {
        Schema::table('marketing_reminders', fn (Blueprint $table) => $table->dropConstrainedForeignId('marketing_lead_id'));
    }
};
