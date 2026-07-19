<?php

namespace App\Support;

class PrintTargets
{
    public static function all(): array
    {
        return [
            'reports.center' => 'Pusat Laporan',
            'reports.construction-progress' => 'Laporan Progress Pembangunan',
            'reports.material-usage' => 'Laporan Pemakaian Material',
            'inventory.module-export' => 'Aset Perusahaan - Ekspor Modul',
            'inventory.issue-report' => 'Aset Perusahaan - Laporan Pengambilan',
            'inventory.transaction' => 'Aset Perusahaan - Bukti Transaksi',
            'heavy.module-export' => 'Alat Berat - Ekspor Modul',
            'heavy.transaction' => 'Alat Berat - Bukti Transaksi',
            'unit.hpp' => 'HPP / RAB Unit Rumah',
            'site-schedule' => 'Jadwal Lapangan',
        ];
    }
}
