<?php

use App\Models\TahapanPembangunan;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        TahapanPembangunan::query()
            ->with('detailRumah:id,perumahan_id')
            ->where('konteks', 'unit')
            ->whereNull('perumahan_id')
            ->whereNotNull('detail_rumah_id')
            ->get()
            ->each(function (TahapanPembangunan $stage): void {
                if ($stage->detailRumah?->perumahan_id) {
                    $stage->update(['perumahan_id' => $stage->detailRumah->perumahan_id]);
                }
            });
    }

    public function down(): void
    {
        // Tidak dikembalikan karena ini perbaikan data relasi yang hilang.
    }
};
