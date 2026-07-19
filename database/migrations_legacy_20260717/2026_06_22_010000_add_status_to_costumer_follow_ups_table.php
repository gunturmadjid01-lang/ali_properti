<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costumer_follow_ups', function (Blueprint $table): void {
            if (! Schema::hasColumn('costumer_follow_ups', 'status')) {
                $table->string('status')->default('selesai')->after('progress_kemampuan');
            }
        });
    }

    public function down(): void
    {
        Schema::table('costumer_follow_ups', function (Blueprint $table): void {
            if (Schema::hasColumn('costumer_follow_ups', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
