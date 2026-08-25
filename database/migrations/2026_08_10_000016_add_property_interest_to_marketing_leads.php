<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_leads', function (Blueprint $table): void {
            $table->foreignId('marketing_campaign_id')->nullable()->after('lead_source_id')->constrained('marketing_campaigns')->nullOnDelete();
            $table->foreignId('cabang_perusahaan_id')->nullable()->after('perumahan_id')->constrained('cabang_perusahaans')->nullOnDelete();
            $table->string('unit_type_interest')->nullable()->after('cabang_perusahaan_id');
            $table->foreignId('detail_rumah_id')->nullable()->after('unit_type_interest')->constrained('detail_rumahs')->nullOnDelete();
            $table->index(['perumahan_id', 'unit_type_interest', 'detail_rumah_id'], 'marketing_leads_property_interest_idx');
        });
    }

    public function down(): void
    {
        Schema::table('marketing_leads', function (Blueprint $table): void {
            $table->dropIndex('marketing_leads_property_interest_idx');
            $table->dropConstrainedForeignId('marketing_campaign_id');
            $table->dropConstrainedForeignId('detail_rumah_id');
            $table->dropConstrainedForeignId('cabang_perusahaan_id');
            $table->dropColumn('unit_type_interest');
        });
    }
};
