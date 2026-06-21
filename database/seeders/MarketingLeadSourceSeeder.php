<?php

namespace Database\Seeders;

use App\Models\MarketingLeadSource;
use Illuminate\Database\Seeder;

class MarketingLeadSourceSeeder extends Seeder
{
    public function run(): void
    {
        $sources = [
            ['kode_sumber' => 'LEAD-FB', 'nama_sumber' => 'Facebook Ads', 'kategori' => 'digital'],
            ['kode_sumber' => 'LEAD-IG', 'nama_sumber' => 'Instagram', 'kategori' => 'digital'],
            ['kode_sumber' => 'LEAD-TT', 'nama_sumber' => 'TikTok', 'kategori' => 'digital'],
            ['kode_sumber' => 'LEAD-WA', 'nama_sumber' => 'WhatsApp Masuk', 'kategori' => 'digital'],
            ['kode_sumber' => 'LEAD-WEB', 'nama_sumber' => 'Website', 'kategori' => 'digital'],
            ['kode_sumber' => 'LEAD-SPANDUK', 'nama_sumber' => 'Spanduk / Baliho', 'kategori' => 'offline'],
            ['kode_sumber' => 'LEAD-BROSUR', 'nama_sumber' => 'Brosur', 'kategori' => 'offline'],
            ['kode_sumber' => 'LEAD-PAMERAN', 'nama_sumber' => 'Pameran / Event', 'kategori' => 'offline'],
            ['kode_sumber' => 'LEAD-REF', 'nama_sumber' => 'Referral', 'kategori' => 'referral'],
            ['kode_sumber' => 'LEAD-AGEN', 'nama_sumber' => 'Agen / Broker', 'kategori' => 'agen'],
            ['kode_sumber' => 'LEAD-WALKIN', 'nama_sumber' => 'Walk-in Lokasi / Kantor', 'kategori' => 'walk_in'],
        ];

        foreach ($sources as $source) {
            MarketingLeadSource::query()->updateOrCreate(
                ['kode_sumber' => $source['kode_sumber']],
                [
                    ...$source,
                    'status' => 'aktif',
                    'record_status' => 'locked',
                ]
            );
        }
    }
}
