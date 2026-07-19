<?php

use App\Models\DetailRumah;
use App\Models\TahapanPembangunan;
use App\Services\HppTemplateService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $templates = app(HppTemplateService::class);
            $templates->refreshSystemTemplates();

            DetailRumah::query()
                ->orderBy('id')
                ->each(fn (DetailRumah $rumah) => $templates->initializeUnit($rumah));

            TahapanPembangunan::query()
                ->where('konteks', 'unit')
                ->whereNull('perumahan_id')
                ->whereNull('detail_rumah_id')
                ->where('nama_tahapan', 'like', 'PEK.%')
                ->get()
                ->each
                ->forceDelete();
        });
    }

    public function down(): void
    {
        // Template sistem tetap menjadi sumber utama; tahapan global tidak dibuat kembali.
    }
};
