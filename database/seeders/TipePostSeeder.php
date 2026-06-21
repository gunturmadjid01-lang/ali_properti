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
                'is_system' => true,
            ],
            [
                'nama_post' => 'DP Cash',
                'jenis' => 'pemasukan',
                'debit' => ChartOfAccount::KAS_BANK,
                'is_system' => true,
            ],
            [
                'nama_post' => 'Cicilan Cash',
                'jenis' => 'pemasukan',
                'debit' => ChartOfAccount::KAS_BANK,
                'is_system' => true,
            ],
            [
                'nama_post' => 'Pelunasan Cash',
                'jenis' => 'pemasukan',
                'debit' => ChartOfAccount::KAS_BANK,
                'is_system' => true,
            ],
            [
                'nama_post' => 'DP KPR',
                'jenis' => 'pemasukan',
                'debit' => ChartOfAccount::KAS_BANK,
                'is_system' => true,
            ],
            [
                'nama_post' => 'Pembayaran KPR',
                'jenis' => 'pemasukan',
                'debit' => ChartOfAccount::KAS_BANK,
                'is_system' => true,
            ],
            [
                'nama_post' => 'Administrasi KPR',
                'jenis' => 'pemasukan',
                'debit' => ChartOfAccount::KAS_BANK,
                'is_system' => true,
            ],
            ['nama_post' => 'Pembayaran Booking Fee', 'jenis' => 'pemasukan'],
            ['nama_post' => 'Pembayaran Uang Muka', 'jenis' => 'pemasukan'],
            ['nama_post' => 'Pelunasan Unit Rumah', 'jenis' => 'pemasukan'],
            ['nama_post' => 'Biaya Operasional Kantor', 'jenis' => 'pengeluaran'],
            ['nama_post' => 'Biaya Material Bangunan', 'jenis' => 'pengeluaran'],
            ['nama_post' => 'Biaya Tenaga Kerja', 'jenis' => 'pengeluaran'],
            [
                'nama_post' => 'Tagihan Kontraktor',
                'jenis' => 'pengeluaran',
                'debit' => ChartOfAccount::HPP_KONSTRUKSI,
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
                'is_system' => (bool) ($post['is_system'] ?? false),
                'record_status' => ($post['is_system'] ?? false) ? 'locked' : 'draft',
                'locked_at' => ($post['is_system'] ?? false) ? now() : null,
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
