<?php

namespace Database\Seeders;

use App\Models\MarketingTemplate;
use Illuminate\Database\Seeder;

class MarketingTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            ['kode_template' => 'TPL-SURVEY', 'nama_template' => 'Konfirmasi Jadwal Survey', 'tahapan' => 'survey', 'isi_template' => 'Halo {nama_customer}, kami dari PT Ali Properti Indonesia ingin mengonfirmasi jadwal survey pada {tanggal_survey}. Mohon konfirmasinya.'],
            ['kode_template' => 'TPL-FOLLOWUP', 'nama_template' => 'Follow Up Setelah Survey', 'tahapan' => 'follow_up', 'isi_template' => 'Halo {nama_customer}, terima kasih sudah melakukan survey unit {unit}. Apakah ada informasi yang ingin ditanyakan lebih lanjut?'],
            ['kode_template' => 'TPL-BOOKING', 'nama_template' => 'Pengingat Booking Fee', 'tahapan' => 'booking_fee', 'isi_template' => 'Halo {nama_customer}, masa booking unit {unit} berlaku sampai {tanggal_expired}. Silakan hubungi kami untuk proses booking fee.'],
            ['kode_template' => 'TPL-DOKUMEN', 'nama_template' => 'Revisi Berkas', 'tahapan' => 'dokumen', 'isi_template' => 'Halo {nama_customer}, berkas {nama_dokumen} perlu diperbaiki. Catatan: {catatan_revisi}.'],
            ['kode_template' => 'TPL-KPR', 'nama_template' => 'Update Proses KPR', 'tahapan' => 'kpr', 'isi_template' => 'Halo {nama_customer}, proses KPR unit {unit} saat ini berada pada tahap {status_kpr}.'],
        ];

        foreach ($templates as $template) {
            MarketingTemplate::query()->updateOrCreate(
                ['kode_template' => $template['kode_template']],
                [
                    ...$template,
                    'kanal' => 'whatsapp',
                    'status' => 'aktif',
                    'is_system' => true,
                    'record_status' => 'locked',
                    'locked_at' => now(),
                ],
            );
        }
    }
}
