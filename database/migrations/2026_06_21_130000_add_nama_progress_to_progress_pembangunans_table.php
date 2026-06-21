<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('progress_pembangunans', function (Blueprint $table) {
            if (! Schema::hasColumn('progress_pembangunans', 'nama_progress')) {
                $table->string('nama_progress')->nullable()->after('tahapan_pembangunan_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('progress_pembangunans', function (Blueprint $table) {
            if (Schema::hasColumn('progress_pembangunans', 'nama_progress')) {
                $table->dropColumn('nama_progress');
            }
        });
    }
};
