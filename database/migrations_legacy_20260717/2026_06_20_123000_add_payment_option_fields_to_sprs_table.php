<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sprs', function (Blueprint $table) {
            if (! Schema::hasColumn('sprs', 'booking_fee_includes_dp')) {
                $table->boolean('booking_fee_includes_dp')->default(false)->after('booking_fee');
            }

            if (! Schema::hasColumn('sprs', 'uang_muka_jumlah_pembayaran')) {
                $table->unsignedSmallInteger('uang_muka_jumlah_pembayaran')->nullable()->after('uang_muka');
            }

            if (! Schema::hasColumn('sprs', 'skema_bertahap')) {
                $table->string('skema_bertahap')->default('cash_bertahap')->after('metode_pembayaran');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sprs', function (Blueprint $table) {
            foreach (['skema_bertahap', 'uang_muka_jumlah_pembayaran', 'booking_fee_includes_dp'] as $column) {
                if (Schema::hasColumn('sprs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
