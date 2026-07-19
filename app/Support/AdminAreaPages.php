<?php

namespace App\Support;

class AdminAreaPages
{
    public static function pages(): array
    {
        return [
            'manager' => [
                'title' => 'Area Manager',
                'description' => 'Pusat kontrol untuk approval, transaksi, laporan, dan pengawasan proyek.',
                'allowed_roles' => ['owner', 'manajer_pimpro'],
                'points' => [
                    'Approval kwitansi dan pengeluaran',
                    'Monitoring hutang dan surat',
                    'Laporan progres proyek',
                    'Pengaturan proyek dan unit',
                ],
                'menus' => [
                    ['label' => 'Approval Kwitansi', 'description' => 'Tinjau dan setujui kwitansi yang diajukan tim.', 'href' => '/admin/approval'],
                    ['label' => 'Approval Pengeluaran', 'description' => 'Validasi transaksi pengeluaran sebelum masuk realisasi.', 'href' => '/admin/approval'],
                    ['label' => 'Hutang', 'description' => 'Pantau hutang proyek, vendor, dan kewajiban pembayaran.', 'href' => '/admin/dashboard'],
                    ['label' => 'Surat', 'description' => 'Kelola surat menyurat dan dokumen pendukung proyek.', 'href' => '/admin/dashboard'],
                    ['label' => 'Transaksi', 'description' => 'Arahkan ke transaksi operasional yang sudah tersedia.', 'href' => '/admin/logistik'],
                    ['label' => 'Laporan', 'description' => 'Buka ringkasan laporan lintas divisi.', 'href' => '/admin/management'],
                    ['label' => 'Setting Proyek', 'description' => 'Atur data proyek, unit, dan kontraktor.', 'href' => '/admin/management'],
                ],
            ],
            'marketing' => [
                'title' => 'Area Marketing',
                'description' => 'Workspace untuk calon konsumen, follow up, SPR, dan laporan penjualan.',
                'allowed_roles' => ['supervisor_marketing', 'area_marketing', 'marketing', 'owner'],
                'points' => [
                    'Calon konsumen dan follow up',
                    'Surat pemesanan rumah',
                    'Operasional marketing',
                    'Laporan penjualan dan lead',
                ],
                'menus' => [
                    ['label' => 'Calon Konsumen', 'description' => 'Input dan pantau data prospek pembeli.', 'href' => '/admin/marketing/calon-konsumen'],
                    ['label' => 'Reservasi Perumahan', 'description' => 'Tahan unit dan terbitkan tagihan Booking Fee.', 'href' => '/admin/marketing/reservasi-perumahan'],
                    ['label' => 'Jejak Follow Up', 'description' => 'Lihat histori tindak lanjut setiap prospek.', 'href' => '/admin/marketing/jejak-follow-up'],
                    ['label' => 'Konsumen', 'description' => 'Kelola data konsumen hasil follow up.', 'href' => '/admin/marketing/konsumen'],
                    ['label' => 'Transaksi SPR', 'description' => 'Proses Surat Pemesanan Rumah dengan skema pembayaran.', 'href' => '/admin/marketing/spr'],
                    ['label' => 'Operasional', 'description' => 'Pantau biaya dan aktivitas marketing.', 'href' => '/admin/marketing/operasional'],
                    ['label' => 'Laporan', 'description' => 'Sales pricelist dan laporan SPR.', 'href' => '/admin/marketing/laporan'],
                ],
            ],
            'admin' => [
                'title' => 'Area Admin',
                'description' => 'Panel kerja admin untuk transaksi, pembayaran, operasional, keuangan, dan laporan.',
                'allowed_roles' => ['owner', 'admin', 'admin_keuangan', 'manajer_pimpro'],
                'points' => [
                    'Transaksi dan pembayaran',
                    'Keuangan pemasukan dan pengeluaran',
                    'Master data proyek',
                    'Laporan operasional',
                ],
                'menus' => [
                    ['label' => 'Transaksi', 'description' => 'Buka halaman transaksi operasional.', 'href' => '/admin/logistik'],
                    ['label' => 'Pembayaran', 'description' => 'Kelola pembayaran masuk dan keluar.', 'href' => '/admin/dashboard'],
                    ['label' => 'Operasional', 'description' => 'Lihat proses dan aktivitas harian.', 'href' => '/admin/logistik'],
                    ['label' => 'Keuangan', 'description' => 'Buka master bank, tipe post, dan HPP.', 'href' => '/admin/management'],
                    ['label' => 'Laporan', 'description' => 'Ringkasan laporan untuk admin.', 'href' => '/admin/management'],
                    ['label' => 'Setting Proyek', 'description' => 'Atur data proyek dan unit perumahan.', 'href' => '/admin/management'],
                ],
            ],
            'teknik' => [
                'title' => 'Area Teknik',
                'description' => 'Kontrol input SPK, progres pembangunan, pembayaran kontraktor, dan status bangunan.',
                'allowed_roles' => ['teknik', 'pengawas', 'admin', 'owner'],
                'points' => [
                    'Input SPK',
                    'Proses bangun',
                    'Proses bayar kontraktor',
                    'Status bangunan dan laporan',
                ],
                'menus' => [
                    ['label' => 'Input SPK', 'description' => 'Buat pengajuan SPK yang menjadi hutang developer.', 'href' => '/admin/dashboard'],
                    ['label' => 'Proses Bangun', 'description' => 'Pantau pekerjaan pembangunan per unit.', 'href' => '/admin/unit-rumah'],
                    ['label' => 'Proses Bayar', 'description' => 'Catat pembayaran ke kontraktor.', 'href' => '/admin/logistik'],
                    ['label' => 'Status Bangunan', 'description' => 'Update progres bangunan dan foto lapangan.', 'href' => '/admin/unit-rumah'],
                    ['label' => 'Laporan', 'description' => 'Laporan teknik dan progres proyek.', 'href' => '/admin/management'],
                ],
            ],
            'admin-proyek' => [
                'title' => 'Area Admin Proyek',
                'description' => 'Fokus pada draft pengeluaran, monitoring pembelian barang, dan status bangunan proyek.',
                'allowed_roles' => ['manajer_pimpro', 'admin', 'owner'],
                'points' => [
                    'Draft pengeluaran',
                    'Item pengeluaran dan data reject',
                    'Proses monitor pembelian',
                    'Laporan keuangan proyek',
                ],
                'menus' => [
                    ['label' => 'Draft Pengeluaran', 'description' => 'Pengeluaran unit/kapling yang menunggu approval manager.', 'href' => '/admin/approval'],
                    ['label' => 'Item Pengeluaran', 'description' => 'Rincian item pembelian per proyek.', 'href' => '/admin/logistik'],
                    ['label' => 'Data Reject', 'description' => 'Lihat transaksi yang ditolak dan alasannya.', 'href' => '/admin/approval'],
                    ['label' => 'Proses Monitor', 'description' => 'Monitor barang yang sudah dibeli dan statusnya.', 'href' => '/admin/logistik'],
                    ['label' => 'Status Bangunan', 'description' => 'Update progress bangunan per unit/kapling.', 'href' => '/admin/unit-rumah'],
                    ['label' => 'Laporan Keuangan', 'description' => 'Ringkasan pengeluaran dan realisasi.', 'href' => '/admin/management'],
                ],
            ],
            'keuangan' => [
                'title' => 'Area Keuangan',
                'description' => 'Panel operasional untuk transaksi keuangan, bank, tipe post, dan pengendalian HPP.',
                'allowed_roles' => ['admin_keuangan', 'admin', 'owner', 'manajer_pimpro'],
                'points' => [
                    'Transaksi pemasukan dan pengeluaran',
                    'Master bank',
                    'Tipe post',
                    'HPP dan realisasi biaya',
                ],
                'menus' => [
                    ['label' => 'Pemasukan', 'description' => 'Kelola item pemasukan dan tipe post pemasukan.', 'href' => '/admin/management/tipe-post'],
                    ['label' => 'Pengeluaran', 'description' => 'Kelola item pengeluaran dan tipe post pengeluaran.', 'href' => '/admin/management/tipe-post'],
                    ['label' => 'List Jenis Pemasukan', 'description' => 'Data master untuk jenis pemasukan.', 'href' => '/admin/management/tipe-post'],
                    ['label' => 'List Jenis Pengeluaran', 'description' => 'Data master untuk jenis pengeluaran.', 'href' => '/admin/management/tipe-post'],
                    ['label' => 'Master Bank', 'description' => 'Akses daftar rekening bank perusahaan.', 'href' => '/admin/management/master-bank'],
                ],
            ],
            'konsumen' => [
                'title' => 'Area Konsumen',
                'description' => 'Ruang kerja untuk data calon konsumen, follow up, booking, dan dokumen customer.',
                'allowed_roles' => ['admin_konsumen', 'marketing', 'admin', 'owner'],
                'points' => [
                    'Data calon konsumen',
                    'Jejak follow up',
                    'Konsumen hasil follow up',
                    'Dokumen customer',
                ],
                'menus' => [
                    ['label' => 'Calon Konsumen', 'description' => 'Input data calon pembeli baru.', 'href' => '/admin/marketing/calon-konsumen'],
                    ['label' => 'Jejak Follow Up', 'description' => 'Lacak interaksi dan status prospek.', 'href' => '/admin/marketing/jejak-follow-up'],
                    ['label' => 'Konsumen', 'description' => 'Kelola konsumen aktif dan booking.', 'href' => '/admin/marketing/konsumen'],
                ],
            ],
            'gudang' => [
                'title' => 'Area Gudang',
                'description' => 'Panel material untuk stok, permintaan barang, dan pergerakan logistik proyek.',
                'allowed_roles' => ['user_area_gudang', 'admin_keuangan', 'admin', 'owner'],
                'points' => [
                    'Barang material',
                    'Stok gudang',
                    'Permintaan material',
                    'Transaksi logistik',
                ],
                'menus' => [
                    ['label' => 'Barang Material', 'description' => 'Master barang dan harga HPP.', 'href' => '/admin/management'],
                    ['label' => 'Stok Gudang', 'description' => 'Pantau stok material masuk dan keluar.', 'href' => '/admin/logistik'],
                    ['label' => 'Permintaan Material', 'description' => 'Permintaan material dari proyek dan unit.', 'href' => '/admin/logistik'],
                    ['label' => 'Transaksi Logistik', 'description' => 'Input transaksi material dan realisasi HPP.', 'href' => '/admin/logistik'],
                ],
            ],
            'kpr' => [
                'title' => 'Area KPR',
                'description' => 'Panel administrasi pembiayaan rumah untuk pengajuan bank dan dokumen pendukung.',
                'allowed_roles' => ['admin_kpr', 'admin', 'owner'],
                'points' => [
                    'Pengajuan KPR',
                    'Kelengkapan dokumen',
                    'Monitoring bank',
                    'Status akad',
                ],
                'menus' => [
                    ['label' => 'Pengajuan KPR', 'description' => 'Kelola data pengajuan pembiayaan.', 'href' => '/admin/kpr'],
                    ['label' => 'Dokumen KPR', 'description' => 'Cek kelengkapan dokumen customer.', 'href' => '/admin/kpr'],
                    ['label' => 'Monitoring Bank', 'description' => 'Pantau status proses per bank.', 'href' => '/admin/management/master-bank'],
                ],
            ],
            'legal' => [
                'title' => 'Area Legal',
                'description' => 'Panel pengelolaan dokumen legalitas proyek dan legalitas unit rumah.',
                'allowed_roles' => ['bag_legal', 'admin', 'owner'],
                'points' => [
                    'Dokumen legalitas perumahan',
                    'Dokumen legalitas rumah',
                    'Status masa berlaku',
                    'Arsip file perizinan',
                ],
                'menus' => [
                    ['label' => 'Dokumen Legalitas', 'description' => 'Kelola dokumen legalitas proyek.', 'href' => '/admin/management/dokumen-legalitas'],
                    ['label' => 'Dokumen Legalitas Rumah', 'description' => 'Kelola dokumen legalitas unit rumah.', 'href' => '/admin/management/dokumen-legalitas-rumah'],
                ],
            ],
            'laporan' => [
                'title' => 'Area Laporan',
                'description' => 'Ringkasan lintas divisi untuk melihat performa proyek, customer, keuangan, dan logistik.',
                'allowed_roles' => ['owner', 'admin', 'manajer_pimpro', 'admin_keuangan', 'supervisor_marketing', 'teknik'],
                'points' => [
                    'Laporan proyek',
                    'Laporan keuangan',
                    'Laporan customer',
                    'Laporan material',
                ],
                'menus' => [
                    ['label' => 'Laporan Proyek', 'description' => 'Ringkasan performa perumahan dan unit.', 'href' => '/admin/management'],
                    ['label' => 'Laporan Keuangan', 'description' => 'Ringkasan transaksi pemasukan/pengeluaran.', 'href' => '/admin/logistik'],
                    ['label' => 'Laporan Customer', 'description' => 'Ringkasan calon konsumen dan SPR.', 'href' => '/admin/dashboard'],
                    ['label' => 'Laporan Material', 'description' => 'Ringkasan stok dan logistik.', 'href' => '/admin/logistik'],
                ],
            ],
        ];
    }

    public static function find(string $slug): array
    {
        return self::pages()[$slug] ?? [];
    }

    public static function allowed(string $slug, array $roles): bool
    {
        $page = self::find($slug);

        if ($page === []) {
            return false;
        }

        if (in_array('super_admin', $roles, true) || in_array('owner', $roles, true)) {
            return true;
        }

        $allowedRoles = $page['allowed_roles'] ?? [];

        if ($allowedRoles === []) {
            return true;
        }

        return (bool) array_intersect($allowedRoles, $roles);
    }
}
