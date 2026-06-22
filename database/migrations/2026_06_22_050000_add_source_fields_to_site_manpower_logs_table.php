<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_manpower_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('site_manpower_logs', 'sumber_tenaga_kerja')) {
                $table->string('sumber_tenaga_kerja')->default('kontraktor')->after('spk_kontraktor_id');
            }
            if (! Schema::hasColumn('site_manpower_logs', 'nama_mandor')) {
                $table->string('nama_mandor')->nullable()->after('kontraktor');
            }
            if (! Schema::hasColumn('site_manpower_logs', 'tipe_upah')) {
                $table->string('tipe_upah')->nullable()->after('kenek');
            }
            if (! Schema::hasColumn('site_manpower_logs', 'nilai_upah')) {
                $table->decimal('nilai_upah', 18, 2)->default(0)->after('tipe_upah');
            }
        });
    }

    public function down(): void
    {
        Schema::table('site_manpower_logs', function (Blueprint $table): void {
            foreach (['nilai_upah', 'tipe_upah', 'nama_mandor', 'sumber_tenaga_kerja'] as $column) {
                if (Schema::hasColumn('site_manpower_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
