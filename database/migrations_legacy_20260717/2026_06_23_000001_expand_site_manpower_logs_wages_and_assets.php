<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_manpower_logs', function (Blueprint $table): void {
            $table->decimal('jumlah_periode', 8, 2)->default(1)->after('tipe_upah');
            $table->decimal('tarif_mandor', 18, 2)->default(0)->after('jumlah_periode');
            $table->decimal('tarif_tukang', 18, 2)->default(0)->after('tarif_mandor');
            $table->decimal('tarif_kenek', 18, 2)->default(0)->after('tarif_tukang');
            $table->decimal('nilai_borongan', 18, 2)->default(0)->after('tarif_kenek');
            $table->decimal('tarif_lembur', 18, 2)->default(0)->after('jam_lembur');
            $table->string('sumber_alat')->default('tidak_ada')->after('tarif_lembur');
            $table->string('penyedia_alat')->nullable()->after('alat_digunakan');
            $table->decimal('biaya_sewa_alat', 18, 2)->default(0)->after('penyedia_alat');
        });

        DB::table('site_manpower_logs')
            ->where('tipe_upah', 'borongan')
            ->update(['nilai_borongan' => DB::raw('nilai_upah')]);

        Schema::create('office_asset_site_manpower_log', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_manpower_log_id')->constrained('site_manpower_logs')->cascadeOnDelete();
            $table->foreignId('office_asset_id')->constrained('office_assets')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['site_manpower_log_id', 'office_asset_id'], 'manpower_asset_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_asset_site_manpower_log');

        Schema::table('site_manpower_logs', function (Blueprint $table): void {
            $table->dropColumn([
                'jumlah_periode',
                'tarif_mandor',
                'tarif_tukang',
                'tarif_kenek',
                'nilai_borongan',
                'tarif_lembur',
                'sumber_alat',
                'penyedia_alat',
                'biaya_sewa_alat',
            ]);
        });
    }
};
