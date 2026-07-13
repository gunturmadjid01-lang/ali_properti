<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('progress_pembangunans', function (Blueprint $table): void {
            if (! Schema::hasColumn('progress_pembangunans', 'schedule_item_key')) {
                $table->string('schedule_item_key')->nullable()->after('site_schedule_id');
            }

            if (! Schema::hasColumn('progress_pembangunans', 'schedule_item_name')) {
                $table->string('schedule_item_name')->nullable()->after('schedule_item_key');
            }

            if (! Schema::hasColumn('progress_pembangunans', 'schedule_item_target')) {
                $table->decimal('schedule_item_target', 8, 2)->default(0)->after('schedule_item_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('progress_pembangunans', function (Blueprint $table): void {
            foreach (['schedule_item_target', 'schedule_item_name', 'schedule_item_key'] as $column) {
                if (Schema::hasColumn('progress_pembangunans', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
