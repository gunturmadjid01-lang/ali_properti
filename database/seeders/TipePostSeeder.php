<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use App\Models\TipePost;
use Illuminate\Database\Seeder;

class TipePostSeeder extends Seeder
{
    public function run(): void
    {
        $posts = [
            [
                'nama_post' => 'Penjualan Unit Cash',
                'jenis' => 'pemasukan',
                'debit' => ChartOfAccount::KAS_BANK,
                'credit' => ChartOfAccount::PENDAPATAN_UNIT,
                'is_system' => true,
            ],
            [
                'nama_post' => 'DP Cash',
                'jenis' => 'pemasukan',
                'debit' => ChartOfAccount::KAS_BANK,
                'credit' => ChartOfAccount::UANG_MUKA_CUSTOMER,
                'is_system' => true,
            ],
            [
                'nama_post' => 'Cicilan Cash',
                'jenis' => 'pemasukan',
                'debit' => ChartOfAccount::KAS_BANK,
                'credit' => ChartOfAccount::UANG_MUKA_CUSTOMER,
                'is_system' => true,
            ],
            [
                'nama_post' => 'Pelunasan Cash',
                'jenis' => 'pemasukan',
                'debit' => ChartOfAccount::KAS_BANK,
                'credit' => ChartOfAccount::PENDAPATAN_UNIT,
                'is_system' => true,
            ],
            [
                'nama_post' => 'DP KPR',
                'jenis' => 'pemasukan',
                'debit' => ChartOfAccount::KAS_BANK,
                'credit' => ChartOfAccount::UANG_MUKA_CUSTOMER,
                'is_system' => true,
            ],
            [
                'nama_post' => 'Pembayaran KPR',
                'jenis' => 'pemasukan',
                'debit' => ChartOfAccount::KAS_BANK,
                'credit' => ChartOfAccount::PENDAPATAN_UNIT,
                'is_system' => true,
            ],
            [
                'nama_post' => 'Administrasi KPR',
                'jenis' => 'pemasukan',
                'debit' => ChartOfAccount::KAS_BANK,
                'credit' => ChartOfAccount::PENDAPATAN_ADMIN,
                'is_system' => true,
            ],
            ['nama_post' => 'Pembayaran Booking Fee', 'jenis' => 'pemasukan', 'debit' => ChartOfAccount::KAS_BANK, 'credit' => ChartOfAccount::UANG_MUKA_CUSTOMER],
            ['nama_post' => 'Booking Fee SPR', 'jenis' => 'pemasukan', 'debit' => ChartOfAccount::KAS_BANK, 'credit' => ChartOfAccount::UANG_MUKA_CUSTOMER],
            ['nama_post' => 'Pembayaran Uang Muka', 'jenis' => 'pemasukan', 'debit' => ChartOfAccount::KAS_BANK, 'credit' => ChartOfAccount::UANG_MUKA_CUSTOMER],
            ['nama_post' => 'Uang Muka SPR', 'jenis' => 'pemasukan', 'debit' => ChartOfAccount::KAS_BANK, 'credit' => ChartOfAccount::UANG_MUKA_CUSTOMER],
            ['nama_post' => 'Pelunasan Unit Rumah', 'jenis' => 'pemasukan', 'debit' => ChartOfAccount::KAS_BANK, 'credit' => ChartOfAccount::PENDAPATAN_UNIT],
            ['nama_post' => 'Pembayaran Lainnya SPR', 'jenis' => 'pemasukan', 'debit' => ChartOfAccount::KAS_BANK, 'credit' => ChartOfAccount::PENDAPATAN_ADMIN],
            ['nama_post' => 'Pengembalian Dana SPR', 'jenis' => 'pengeluaran', 'debit' => ChartOfAccount::UANG_MUKA_CUSTOMER, 'credit' => ChartOfAccount::KAS_BANK],
            ['nama_post' => 'Biaya Operasional Kantor', 'jenis' => 'pengeluaran', 'debit' => ChartOfAccount::BEBAN_OPERASIONAL, 'credit' => ChartOfAccount::KAS_BANK],
            ['nama_post' => 'Biaya Material Bangunan', 'jenis' => 'pengeluaran', 'debit' => ChartOfAccount::HPP_MATERIAL, 'credit' => ChartOfAccount::KAS_BANK],
            ['nama_post' => 'Biaya Tenaga Kerja', 'jenis' => 'pengeluaran', 'debit' => '6-1000', 'credit' => ChartOfAccount::KAS_BANK],
            ['nama_post' => 'Setoran Modal Awal', 'jenis' => 'pemasukan', 'debit' => ChartOfAccount::KAS_BANK, 'credit' => '3-1000'],
            ['nama_post' => 'Investasi Investor - Penyertaan Modal', 'jenis' => 'pemasukan', 'debit' => ChartOfAccount::KAS_BANK, 'credit' => '3-1100'],
            ['nama_post' => 'Pinjaman Investor', 'jenis' => 'pemasukan', 'debit' => ChartOfAccount::KAS_BANK, 'credit' => ChartOfAccount::HUTANG_INVESTOR],
            ['nama_post' => 'Pengembalian Pinjaman Investor', 'jenis' => 'pengeluaran', 'debit' => ChartOfAccount::HUTANG_INVESTOR, 'credit' => ChartOfAccount::KAS_BANK],
            ['nama_post' => 'Pendapatan Lain-lain', 'jenis' => 'pemasukan', 'debit' => ChartOfAccount::KAS_BANK, 'credit' => '4-9000'],
            ['nama_post' => 'Beban Lain-lain', 'jenis' => 'pengeluaran', 'debit' => '6-9000', 'credit' => ChartOfAccount::KAS_BANK],
            [
                'nama_post' => 'Tagihan Kontraktor',
                'jenis' => 'pengeluaran',
                'debit' => ChartOfAccount::PERSEDIAAN_PROYEK,
                'credit' => ChartOfAccount::HUTANG_KONTRAKTOR,
                'is_system' => true,
            ],
            [
                'nama_post' => 'Pembayaran Hutang Kontraktor',
                'jenis' => 'pengeluaran',
                'debit' => ChartOfAccount::HUTANG_KONTRAKTOR,
                'credit' => ChartOfAccount::KAS_BANK,
                'is_system' => true,
            ],
            [
                'nama_post' => 'Pembelian Material',
                'jenis' => 'pengeluaran',
                'debit' => ChartOfAccount::PERSEDIAAN_MATERIAL,
                'credit' => ChartOfAccount::KAS_BANK,
                'is_system' => true,
            ],
            [
                'nama_post' => 'Hutang Supplier',
                'jenis' => 'pengeluaran',
                'debit' => ChartOfAccount::PERSEDIAAN_MATERIAL,
                'credit' => ChartOfAccount::HUTANG_SUPPLIER,
                'is_system' => true,
            ],
            [
                'nama_post' => 'Pembayaran Hutang Supplier',
                'jenis' => 'pengeluaran',
                'debit' => ChartOfAccount::HUTANG_SUPPLIER,
                'credit' => ChartOfAccount::KAS_BANK,
                'is_system' => true,
            ],
        ];

        foreach ($posts as $post) {
            $debitAccountId = isset($post['debit'])
                ? ChartOfAccount::query()->where('kode_akun', $post['debit'])->value('id')
                : null;
            $creditAccountId = isset($post['credit'])
                ? ChartOfAccount::query()->where('kode_akun', $post['credit'])->value('id')
                : null;
            $data = [
                'nama_post' => $post['nama_post'],
                'jenis' => $post['jenis'],
                'status' => 'aktif',
                'debit_account_id' => $debitAccountId,
                'credit_account_id' => $creditAccountId,
                'is_system' => true,
                'record_status' => 'locked',
                'locked_at' => now(),
            ];
            $row = TipePost::withTrashed()
                ->where('nama_post', $post['nama_post'])
                ->first();

            if ($row) {
                $row->fill($data)->save();

                if ($row->trashed()) {
                    $row->restore();
                }

                continue;
            }

            TipePost::create($data);
        }
    }
}
