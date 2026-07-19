<?php

use App\Models\TahapanPembangunan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $oldUnitStages = [
        'I PEKERJAAN PONDASI',
        'II PEKERJAAN KONSTRUKSI DINDING',
        'III PEKERJAAN ATAP & PLAFON',
        'IV PEKERJAAN KUSEN, PINTU & JENDELA',
        'V PEKERJAAN KERAMIK & SANITARI',
        'VI PEKERJAAN TAMBAHAN',
        'VII PEKERJAAN PENGECATAN',
    ];

    private array $newUnitStages = [
        ['PEK. PERSIAPAN & PONDASI', 1, 7.48],
        ['PEK. DINDING', 2, 26.30],
        ['PEK. FINISHING AWAL', 3, 14.44],
        ['PEK. PIPA AIR BERSIH & KOTOR', 4, 1.66],
        ['PEK. KERAMIK, SANITASI, PINTU & KAMAR MANDI', 5, 10.81],
        ['PEK. PAGAR & CAR PORT', 6, 14.96],
        ['PEK. TAMAN, PROFIL DAN PENGECATAN', 7, 6.38],
        ['PEK. PEMASANGAN ATAP', 8, 7.42],
        ['PEK. PEMASANGAN PLAFON', 9, 7.42],
        ['PEK. INSTALASI LISTRIK', 10, 3.13],
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            TahapanPembangunan::query()
                ->where('konteks', 'unit')
                ->whereIn('nama_tahapan', $this->oldUnitStages)
                ->update(['status' => 'nonaktif', 'updated_at' => now()]);

            $scopes = TahapanPembangunan::query()
                ->where('konteks', 'unit')
                ->select('perumahan_id', 'detail_rumah_id')
                ->distinct()
                ->get();

            if ($scopes->isEmpty()) {
                $scopes = collect([(object) ['perumahan_id' => null, 'detail_rumah_id' => null]]);
            }

            foreach ($scopes as $scope) {
                foreach ($this->newUnitStages as [$name, $order, $weight]) {
                    TahapanPembangunan::query()->updateOrCreate(
                        [
                            'nama_tahapan' => $name,
                            'konteks' => 'unit',
                            'perumahan_id' => $scope->perumahan_id,
                            'detail_rumah_id' => $scope->detail_rumah_id,
                        ],
                        [
                            'urutan' => $order,
                            'bobot_persen' => $weight,
                            'status' => 'aktif',
                        ],
                    );
                }
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            TahapanPembangunan::query()
                ->where('konteks', 'unit')
                ->whereIn('nama_tahapan', collect($this->newUnitStages)->pluck(0)->all())
                ->update(['status' => 'nonaktif', 'updated_at' => now()]);

            TahapanPembangunan::query()
                ->where('konteks', 'unit')
                ->whereIn('nama_tahapan', $this->oldUnitStages)
                ->update(['status' => 'aktif', 'updated_at' => now()]);
        });
    }
};
