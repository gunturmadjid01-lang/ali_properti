<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detail_rumahs', function (Blueprint $table) {
            if (! Schema::hasColumn('detail_rumahs', 'booking_spr_id')) {
                $table->foreignId('booking_spr_id')->nullable()->after('status_penjualan')->constrained('sprs')->nullOnDelete();
            }

            if (! Schema::hasColumn('detail_rumahs', 'booking_at')) {
                $table->timestamp('booking_at')->nullable()->after('booking_spr_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('detail_rumahs', function (Blueprint $table) {
            if (Schema::hasColumn('detail_rumahs', 'booking_at')) {
                $table->dropColumn('booking_at');
            }

            if (Schema::hasColumn('detail_rumahs', 'booking_spr_id')) {
                $table->dropConstrainedForeignId('booking_spr_id');
            }
        });
    }
};
