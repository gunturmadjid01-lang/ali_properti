<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use Illuminate\Database\Seeder;

class ChartOfAccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            [ChartOfAccount::KAS_BANK, 'Kas/Bank', 'aset', 'debit'],
            [ChartOfAccount::PERSEDIAAN_MATERIAL, 'Persediaan Material', 'aset', 'debit'],
            [ChartOfAccount::HUTANG_KONTRAKTOR, 'Hutang Kontraktor', 'liabilitas', 'kredit'],
            [ChartOfAccount::HUTANG_SUPPLIER, 'Hutang Supplier', 'liabilitas', 'kredit'],
            [ChartOfAccount::HPP_KONSTRUKSI, 'HPP Konstruksi', 'beban_hpp', 'debit'],
            [ChartOfAccount::HPP_MATERIAL, 'HPP Material', 'beban_hpp', 'debit'],
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
