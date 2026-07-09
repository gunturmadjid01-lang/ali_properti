<?php

use App\Models\DetailRumahHppItem;
use App\Models\TahapanPembangunan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

return new class extends Migration
{
    private array $directMap = [
        'I PEKERJAAN PONDASI' => 'PEK. PERSIAPAN & PONDASI',
        'II PEKERJAAN KONSTRUKSI DINDING' => 'PEK. DINDING',
        'IV PEKERJAAN KUSEN, PINTU & JENDELA' => 'PEK. KERAMIK, SANITASI, PINTU & KAMAR MANDI',
        'VII PEKERJAAN PENGECATAN' => 'PEK. TAMAN, PROFIL DAN PENGECATAN',
        'VII PENGECATAN' => 'PEK. TAMAN, PROFIL DAN PENGECATAN',
    ];

    private array $newStages = [
        'PEK. PERSIAPAN & PONDASI' => [1, 7.48],
        'PEK. DINDING' => [2, 26.30],
        'PEK. FINISHING AWAL' => [3, 14.44],
        'PEK. PIPA AIR BERSIH & KOTOR' => [4, 1.66],
        'PEK. KERAMIK, SANITASI, PINTU & KAMAR MANDI' => [5, 10.81],
        'PEK. PAGAR & CAR PORT' => [6, 14.96],
        'PEK. TAMAN, PROFIL DAN PENGECATAN' => [7, 6.38],
        'PEK. PEMASANGAN ATAP' => [8, 7.42],
        'PEK. PEMASANGAN PLAFON' => [9, 7.42],
        'PEK. INSTALASI LISTRIK' => [10, 3.13],
    ];

    public function up(): void
    {
        DetailRumahHppItem::query()
            ->with(['tahapanPembangunan', 'detailRumahHpp.detailRumah:id,perumahan_id'])
            ->get()
            ->each(function (DetailRumahHppItem $item): void {
                $oldName = $item->tahapanPembangunan?->nama_tahapan;
                if (! $oldName) {
                    return;
                }

                $newName = $this->targetStageName($oldName, $item->nama_pekerjaan ?? '');
                if (! $newName || $oldName === $newName) {
                    return;
                }

                $detailRumah = $item->detailRumahHpp?->detailRumah;
                $stage = $this->stageFor($newName, $detailRumah?->perumahan_id, $detailRumah?->id);
                $item->update(['tahapan_pembangunan_id' => $stage->id]);
            });
    }

    public function down(): void
    {
        // Tidak dikembalikan otomatis karena mapping lama 7 tahap tidak sepadan 1:1 dengan 10 PEK.
    }

    private function targetStageName(string $oldName, string $jobName): ?string
    {
        if (isset($this->directMap[$oldName])) {
            return $this->directMap[$oldName];
        }

        $job = Str::lower($jobName);

        if (in_array($oldName, ['III PEKERJAAN ATAP & PLAFON', 'III PEKERJAAN ATAP DAN PLAFON'], true)) {
            return Str::contains($job, ['gypsum', 'calsibord', 'plafon', 'holow'])
                ? 'PEK. PEMASANGAN PLAFON'
                : 'PEK. PEMASANGAN ATAP';
        }

        if (in_array($oldName, ['V PEKERJAAN KERAMIK & SANITARI', 'V PEKERJAAN KERAMIK, DINDING & SANITARI'], true)) {
            return Str::contains($job, ['pipa', 'air bersih', 'air kotor', 'pembuangan'])
                ? 'PEK. PIPA AIR BERSIH & KOTOR'
                : 'PEK. KERAMIK, SANITASI, PINTU & KAMAR MANDI';
        }

        if ($oldName === 'VI PEKERJAAN TAMBAHAN') {
            return Str::contains($job, ['kabel', 'lampu', 'saklar', 'listrik'])
                ? 'PEK. INSTALASI LISTRIK'
                : 'PEK. PERSIAPAN & PONDASI';
        }

        return null;
    }

    private function stageFor(string $name, int|string|null $perumahanId, int|string|null $detailRumahId): TahapanPembangunan
    {
        [$order, $weight] = $this->newStages[$name] ?? [999, 0];

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
