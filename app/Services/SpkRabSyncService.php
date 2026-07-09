<?php

namespace App\Services;

use App\Models\DetailPerumahanHpp;
use App\Models\KelompokHpp;
use App\Models\PerumahanHpp;
use App\Models\SpkKontraktor;
use App\Models\TahapanPembangunan;
use Illuminate\Support\Facades\DB;

class SpkRabSyncService
{
    public function sync(SpkKontraktor $spk): ?DetailPerumahanHpp
    {
        if (! $spk->perumahan_id) {
            return null;
        }

        return DB::transaction(function () use ($spk): ?DetailPerumahanHpp {
            $perumahanHpp = PerumahanHpp::query()->firstOrCreate(
                ['perumahan_id' => $spk->perumahan_id],
                [
                    'user_id' => auth()->id() ?? $spk->created_by,
                    'tanggal_dibuat' => now()->toDateString(),
                ],
            );

            $stage = TahapanPembangunan::query()->firstOrCreate(
                [
                    'perumahan_id' => $spk->perumahan_id,
                    'detail_rumah_id' => null,
                    'konteks' => 'kawasan',
                    'nama_tahapan' => 'IV RAB BANGUNAN',
                ],
                [
                    'bobot_persen' => 0,
                    'urutan' => 4,
                    'status' => 'aktif',
                ],
            );

            $namaPekerjaan = $this->rabLabel($spk);
            $kelompokId = $this->kelompokIdForJenis($spk->jenis_pekerjaan);
            $jumlahRab = (float) $spk->nilai_kontrak;

            return $perumahanHpp->detailPerumahanHpps()->updateOrCreate(
                [
                    'tahapan_pembangunan_id' => $stage->id,
                    'nama_pekerjaan' => $namaPekerjaan,
                ],
                [
                    'kelompok_hpp_id' => $kelompokId,
                    'volume' => 1,
                    'satuan' => 'LS',
                    'harga_satuan' => $jumlahRab,
                    'jumlah_rab' => $jumlahRab,
                    'urutan' => 999,
                ],
            );
        });
    }

    protected function rabLabel(SpkKontraktor $spk): string
    {
        return 'SPK '.$spk->nomor_spk.' - '.$this->jenisLabel($spk->jenis_pekerjaan);
    }

    protected function jenisLabel(?string $jenis): string
    {
        return match ($jenis) {
            'jalan' => 'Pekerjaan Jalan',
            'pembukaan_lahan' => 'Pekerjaan Pembukaan Lahan',
            'rumah' => 'Pekerjaan Rumah',
            default => 'Pekerjaan Lainnya',
        };
    }

    protected function kelompokIdForJenis(?string $jenis): ?int
    {
        $nama = match ($jenis) {
            'jalan' => 'Jalan Kawasan',
            'pembukaan_lahan' => 'Biaya Pematangan Lahan',
            'rumah' => 'Biaya Subkontraktor',
            default => 'Biaya Subkontraktor',
        };

        return KelompokHpp::query()->where('nama_hpp', $nama)->value('id')
            ?? KelompokHpp::query()->orderBy('id')->value('id');
    }
}
