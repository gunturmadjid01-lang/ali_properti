<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kpr_follow_ups', function (Blueprint $table) {
            $table->text('hasil_follow_up')->nullable()->after('status_kpr');
            $table->text('kendala')->nullable()->after('hasil_follow_up');
            $table->text('tindak_lanjut')->nullable()->after('kendala');
        });
    }

    public function down(): void
    {
        Schema::table('kpr_follow_ups', function (Blueprint $table) {
            $table->dropColumn(['hasil_follow_up', 'kendala', 'tindak_lanjut']);
        });
    }
};
