<?php

use App\Models\DetailRumahHpp;
use App\Models\TahapanPembangunan;
use App\Services\HppTemplateService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            DetailRumahHpp::query()
                ->with('detailRumah:id,perumahan_id')
                ->whereHas('items')
                ->each(function (DetailRumahHpp $hpp): void {
                    $rumah = $hpp->detailRumah;

                    if (! $rumah) {
                        return;
                    }

                    $finishing = $this->stage(
                        'PEK. FINISHING AWAL',
                        3,
                        14.44,
                        $rumah->perumahan_id,
                        $rumah->id,
                    );
                    $pagar = $this->stage(
                        'PEK. PAGAR & CAR PORT',
                        6,
                        14.96,
                        $rumah->perumahan_id,
                        $rumah->id,
                    );

                    // RAB lama menggabungkan pekerjaan dinding dengan pekerjaan
                    // finishing. Pisahkan bahan finishing ke tahap jadwalnya.
                    $hpp->items()
                        ->where(function ($query): void {
                            $query->where('nama_pekerjaan', 'like', '%Semen Perekat%')
                                ->orWhere('nama_pekerjaan', 'like', '%Acian%');
                        })
                        ->update(['tahapan_pembangunan_id' => $finishing->id]);

                    // Item "pekerjaan tambahan" ini adalah bahan pekerjaan luar
                    // bangunan dan menjadi isi tahap pagar/car port.
                    $hpp->items()
                        ->where('nama_pekerjaan', 'like', '%material balok%')
                        ->update(['tahapan_pembangunan_id' => $pagar->id]);
                });

            app(HppTemplateService::class)->refreshSystemTemplates();
        });
    }

    public function down(): void
    {
        // Pemetaan tidak dibalik agar nilai RAB yang sudah diedit pengguna aman.
    }

    private function stage(
        string $name,
        int $order,
        float $weight,
        int $perumahanId,
        int $detailRumahId,
    ): TahapanPembangunan {
        return TahapanPembangunan::query()->updateOrCreate(
            [
                'nama_tahapan' => $name,
                'konteks' => 'unit',
                'perumahan_id' => $perumahanId,
                'detail_rumah_id' => $detailRumahId,
            ],
            [
                'urutan' => $order,
                'bobot_persen' => $weight,
                'status' => 'aktif',
            ],
        );
    }
};
