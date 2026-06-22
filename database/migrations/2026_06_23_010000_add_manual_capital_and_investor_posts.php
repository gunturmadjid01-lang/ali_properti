<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $accounts = [
            ['2-2500', 'Hutang Investor', 'liabilitas', 'kredit'],
            ['3-1100', 'Modal Penyertaan Investor', 'ekuitas', 'kredit'],
        ];

        foreach ($accounts as [$code, $name, $category, $normal]) {
            DB::table('chart_of_accounts')->updateOrInsert(
                ['kode_akun' => $code],
                [
                    'nama_akun' => $name,
                    'kategori' => $category,
                    'posisi_normal' => $normal,
                    'status' => 'aktif',
                    'is_system' => true,
                    'deleted_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        $accountIds = DB::table('chart_of_accounts')->pluck('id', 'kode_akun');
        $posts = [
            ['Setoran Modal Owner', 'pemasukan', '1-1000', '3-1000'],
            ['Investasi Investor - Penyertaan Modal', 'pemasukan', '1-1000', '3-1100'],
            ['Pinjaman Investor', 'pemasukan', '1-1000', '2-2500'],
            ['Pengembalian Pinjaman Investor', 'pengeluaran', '2-2500', '1-1000'],
            ['Pendapatan Lain-lain', 'pemasukan', '1-1000', '4-9000'],
            ['Beban Lain-lain', 'pengeluaran', '6-9000', '1-1000'],
        ];

        foreach ($posts as [$name, $type, $debitCode, $creditCode]) {
            DB::table('tipe_posts')->updateOrInsert(
                ['nama_post' => $name],
                [
                    'jenis' => $type,
                    'debit_account_id' => $accountIds[$debitCode],
                    'credit_account_id' => $accountIds[$creditCode],
                    'status' => 'aktif',
                    'is_system' => true,
                    'record_status' => 'locked',
                    'locked_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    public function down(): void
    {
        DB::table('tipe_posts')->whereIn('nama_post', [
            'Setoran Modal Owner',
            'Investasi Investor - Penyertaan Modal',
            'Pinjaman Investor',
            'Pengembalian Pinjaman Investor',
            'Pendapatan Lain-lain',
            'Beban Lain-lain',
        ])->delete();
    }
};
