<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_campaigns', function (Blueprint $table): void {
            $table->foreignId('perumahan_id')->nullable()->after('id')->constrained('perumahans')->nullOnDelete();
        });

        Schema::table('marketing_targets', function (Blueprint $table): void {
            $table->dropUnique(['user_id', 'tahun', 'bulan']);
            $table->foreignId('perumahan_id')->nullable()->after('id')->constrained('perumahans')->nullOnDelete();
            $table->unique(['perumahan_id', 'user_id', 'tahun', 'bulan'], 'marketing_targets_property_user_period_unique');
        });

        DB::statement('
            update marketing_campaigns
            set perumahan_id = (
                select costumers.perumahan_id
                from costumers
                where costumers.marketing_campaign_id = marketing_campaigns.id
                  and costumers.perumahan_id is not null
                order by costumers.id desc
                limit 1
            )
            where perumahan_id is null
        ');

        DB::statement('
            update marketing_targets
            set perumahan_id = (
                select perumahan_user.perumahan_id
                from perumahan_user
                where perumahan_user.user_id = marketing_targets.user_id
                order by perumahan_user.id
                limit 1
            )
            where perumahan_id is null
        ');
    }

    public function down(): void
    {
        Schema::table('marketing_targets', function (Blueprint $table): void {
            $table->dropUnique('marketing_targets_property_user_period_unique');
            $table->dropConstrainedForeignId('perumahan_id');
            $table->unique(['user_id', 'tahun', 'bulan']);
        });

        Schema::table('marketing_campaigns', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('perumahan_id');
        });
    }
};
