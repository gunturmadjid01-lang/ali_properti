<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('material_requests', 'kelompok_hpp_id')) {
                $table->dropConstrainedForeignId('kelompok_hpp_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('material_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('material_requests', 'kelompok_hpp_id')) {
                $table->foreignId('kelompok_hpp_id')->nullable()->after('tahapan_pembangunan_id')->constrained('kelompok_hpps')->nullOnDelete();
            }
        });
    }
};
