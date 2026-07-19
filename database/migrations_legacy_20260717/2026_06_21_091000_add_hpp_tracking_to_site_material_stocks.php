<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_material_stocks', function (Blueprint $table) {
            $table->foreignId('kelompok_hpp_id')->nullable()->after('tahapan_pembangunan_id')->constrained('kelompok_hpps')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('site_material_stocks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kelompok_hpp_id');
        });
    }
};
