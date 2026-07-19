<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_schedules', function (Blueprint $table): void {
            if (! Schema::hasColumn('site_schedules', 'spk_plan_json')) {
                $table->json('spk_plan_json')->nullable()->after('spk_kontraktor_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('site_schedules', function (Blueprint $table): void {
            if (Schema::hasColumn('site_schedules', 'spk_plan_json')) {
                $table->dropColumn('spk_plan_json');
            }
        });
    }
};
