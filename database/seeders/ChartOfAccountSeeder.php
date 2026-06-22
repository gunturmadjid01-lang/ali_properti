<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use Illuminate\Database\Seeder;

class ChartOfAccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            [ChartOfAccount::KAS_BANK, 'Kas dan Bank', 'aset', 'debit'],
            [ChartOfAccount::PIUTANG_CUSTOMER, 'Piutang Usaha Customer', 'aset', 'debit'],
            ['1-1200', 'Piutang Lain-lain', 'aset', 'debit'],
            [ChartOfAccount::PERSEDIAAN_MATERIAL, 'Persediaan Material', 'aset', 'debit'],
            [ChartOfAccount::PERSEDIAAN_PROYEK, 'Persediaan Tanah dan Bangunan', 'aset', 'debit'],
            ['1-1500', 'Uang Muka Pembelian', 'aset', 'debit'],
            ['1-1600', 'Aset Tetap', 'aset', 'debit'],
            ['1-1690', 'Akumulasi Penyusutan', 'aset_kontra', 'kredit'],
            [ChartOfAccount::UANG_MUKA_CUSTOMER, 'Uang Muka Customer', 'liabilitas', 'kredit'],
            [ChartOfAccount::HUTANG_KONTRAKTOR, 'Hutang Kontraktor', 'liabilitas', 'kredit'],
            [ChartOfAccount::HUTANG_SUPPLIER, 'Hutang Supplier', 'liabilitas', 'kredit'],
            ['2-2300', 'Hutang Operasional', 'liabilitas', 'kredit'],
            ['2-2400', 'Hutang Pajak', 'liabilitas', 'kredit'],
            [ChartOfAccount::HUTANG_INVESTOR, 'Hutang Investor', 'liabilitas', 'kredit'],
            ['3-1000', 'Modal Disetor', 'ekuitas', 'kredit'],
            ['3-1100', 'Modal Penyertaan Investor', 'ekuitas', 'kredit'],
            ['3-2000', 'Laba Ditahan', 'ekuitas', 'kredit'],
            [ChartOfAccount::PENDAPATAN_UNIT, 'Pendapatan Penjualan Unit', 'pendapatan', 'kredit'],
            [ChartOfAccount::PENDAPATAN_ADMIN, 'Pendapatan Administrasi', 'pendapatan', 'kredit'],
            ['4-9000', 'Pendapatan Lain-lain', 'pendapatan_lain', 'kredit'],
            [ChartOfAccount::HPP_KONSTRUKSI, 'HPP Konstruksi', 'beban_hpp', 'debit'],
            [ChartOfAccount::HPP_MATERIAL, 'HPP Material', 'beban_hpp', 'debit'],
            ['5-1300', 'HPP Tanah dan Perizinan', 'beban_hpp', 'debit'],
            ['6-1000', 'Beban Gaji dan Tenaga Kerja', 'beban_operasional', 'debit'],
            ['6-2000', 'Beban Marketing dan Promosi', 'beban_operasional', 'debit'],
            [ChartOfAccount::BEBAN_OPERASIONAL, 'Beban Kantor dan Umum', 'beban_operasional', 'debit'],
            ['6-4000', 'Beban Utilitas', 'beban_operasional', 'debit'],
            ['6-5000', 'Beban Penyusutan', 'beban_operasional', 'debit'],
            ['6-6000', 'Beban Bank dan Administrasi', 'beban_operasional', 'debit'],
            ['6-9000', 'Beban Lain-lain', 'beban_lain', 'debit'],
        ];

        foreach ($accounts as [$kode, $nama, $kategori, $normal]) {
            $account = ChartOfAccount::withTrashed()->updateOrCreate(
                ['kode_akun' => $kode],
                ['nama_akun' => $nama, 'kategori' => $kategori, 'posisi_normal' => $normal, 'status' => 'aktif', 'is_system' => true],
            );

            if ($account->trashed()) {
                $account->restore();
            }
        }
    }
}
