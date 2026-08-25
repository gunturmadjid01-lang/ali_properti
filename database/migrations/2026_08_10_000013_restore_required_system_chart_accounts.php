<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $accounts = [
            ['1-1000', 'Kas Bank', 'aset', 'debit'],
            ['1-1010', 'Kas Kecil', 'aset', 'debit'],
            ['1-1100', 'Piutang Customer', 'aset', 'debit'],
            ['1-1400', 'Persediaan Proyek', 'aset', 'debit'],
            ['2-1000', 'Uang Muka Customer', 'kewajiban', 'kredit'],
            ['2-2100', 'Hutang Kontraktor', 'kewajiban', 'kredit'],
            ['2-2200', 'Hutang Supplier', 'kewajiban', 'kredit'],
            ['2-2500', 'Hutang Investor', 'kewajiban', 'kredit'],
            ['4-1000', 'Pendapatan Penjualan Unit', 'pendapatan', 'kredit'],
            ['4-2000', 'Pendapatan Administrasi', 'pendapatan', 'kredit'],
            ['5-1100', 'HPP Konstruksi', 'beban', 'debit'],
            ['5-1200', 'HPP Material', 'beban', 'debit'],
            ['6-1000', 'Beban Gaji', 'beban', 'debit'],
            ['6-3000', 'Beban Operasional', 'beban', 'debit'],
        ];

        foreach ($accounts as [$code, $name, $category, $normalBalance]) {
            $values = [
                'nama_akun' => $name,
                'kategori' => $category,
                'posisi_normal' => $normalBalance,
                'status' => 'aktif',
                'is_system' => true,
                'deleted_at' => null,
                'updated_at' => now(),
            ];

            if (DB::table('chart_of_accounts')->where('kode_akun', $code)->exists()) {
                DB::table('chart_of_accounts')->where('kode_akun', $code)->update($values);
            } else {
                DB::table('chart_of_accounts')->insert(['kode_akun' => $code, ...$values, 'created_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        // Akun sistem tidak dihapus karena mungkin sudah dipakai jurnal transaksi.
    }
};
