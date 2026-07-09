<?php

use App\Models\TahapanPembangunan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            TahapanPembangunan::query()
                ->where('status', 'aktif')
                ->where('konteks', 'unit')
                ->whereNotNull('detail_rumah_id')
                ->whereDoesntHave('detailRumah')
                ->update([
                    'status' => 'nonaktif',
                    'updated_at' => now(),
                ]);

            TahapanPembangunan::query()
                ->where('status', 'aktif')
                ->whereNotNull('perumahan_id')
                ->whereDoesntHave('perumahan')
                ->update([
                    'status' => 'nonaktif',
                    'updated_at' => now(),
                ]);
        });
    }

    public function down(): void
    {
        // Tahap yatim tidak diaktifkan kembali karena induknya telah dihapus.
    }
};
