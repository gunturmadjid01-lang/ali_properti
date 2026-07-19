<?php

namespace Database\Seeders;

use App\Models\DokumenCostumer;
use Illuminate\Database\Seeder;

class DokumenCostumerSeeder extends Seeder
{
    public function run(): void
    {
        $documents = [
            ['kode_dokumen' => 'KTP', 'nama_dokumen' => 'KTP Customer', 'kategori_pengajuan' => 'spr'],
            ['kode_dokumen' => 'KK', 'nama_dokumen' => 'Kartu Keluarga', 'kategori_pengajuan' => 'spr'],
            ['kode_dokumen' => 'NPWP', 'nama_dokumen' => 'NPWP Customer', 'kategori_pengajuan' => 'kpr_bank'],
            ['kode_dokumen' => 'SLIP-GAJI', 'nama_dokumen' => 'Slip Gaji', 'kategori_pengajuan' => 'kpr_bank'],
            ['kode_dokumen' => 'REK-KORAN', 'nama_dokumen' => 'Rekening Koran', 'kategori_pengajuan' => 'kpr_bank'],
            ['kode_dokumen' => 'SURAT-KERJA', 'nama_dokumen' => 'Surat Keterangan Kerja', 'kategori_pengajuan' => 'kpr_bank'],
            ['kode_dokumen' => 'BUKTI-BOOKING', 'nama_dokumen' => 'Bukti Booking Fee', 'kategori_pengajuan' => 'spr'],
            ['kode_dokumen' => 'SURAT-CB', 'nama_dokumen' => 'Surat Kesanggupan Pembayaran Bertahap', 'kategori_pengajuan' => 'cash_bertahap'],
            ['kode_dokumen' => 'JADWAL-CB', 'nama_dokumen' => 'Persetujuan Jadwal Cash Bertahap', 'kategori_pengajuan' => 'cash_bertahap'],
            ['kode_dokumen' => 'KPRD-SLIP', 'nama_dokumen' => 'Slip Gaji KPR Developer', 'kategori_pengajuan' => 'kpr_developer'],
            ['kode_dokumen' => 'KPRD-REK', 'nama_dokumen' => 'Rekening Koran KPR Developer', 'kategori_pengajuan' => 'kpr_developer'],
            ['kode_dokumen' => 'KPRD-SETUJU', 'nama_dokumen' => 'Surat Persetujuan Pasangan KPR Developer', 'kategori_pengajuan' => 'kpr_developer'],
        ];

        foreach ($documents as $document) {
            $row = DokumenCostumer::withTrashed()
                ->where('kode_dokumen', $document['kode_dokumen'])
                ->first();

            $data = [
                ...$document,
                'wajib' => true,
                'keterangan' => null,
                'status' => 'aktif',
            ];

            if ($row) {
                $row->fill($data)->save();

                if ($row->trashed()) {
                    $row->restore();
                }

                continue;
            }

            DokumenCostumer::create($data);
        }
    }
}
