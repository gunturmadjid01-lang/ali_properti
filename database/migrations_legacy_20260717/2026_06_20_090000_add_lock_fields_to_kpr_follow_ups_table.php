<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kpr_follow_ups', function (Blueprint $table) {
            if (! Schema::hasColumn('kpr_follow_ups', 'record_status')) {
                $table->string('record_status')->default('draft')->after('status_kpr');
            }

            if (! Schema::hasColumn('kpr_follow_ups', 'locked_at')) {
                $table->timestamp('locked_at')->nullable()->after('record_status');
            }

            if (! Schema::hasColumn('kpr_follow_ups', 'locked_by')) {
                $table->foreignId('locked_by')->nullable()->after('locked_at')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('kpr_follow_ups', function (Blueprint $table) {
            if (Schema::hasColumn('kpr_follow_ups', 'locked_by')) {
                $table->dropConstrainedForeignId('locked_by');
            }

            if (Schema::hasColumn('kpr_follow_ups', 'locked_at')) {
                $table->dropColumn('locked_at');
            }

            if (Schema::hasColumn('kpr_follow_ups', 'record_status')) {
                $table->dropColumn('record_status');
            }
        });
    }
};
