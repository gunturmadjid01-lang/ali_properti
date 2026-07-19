<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // Fondasi akses dan struktur perusahaan.
            PropertyAreaRoleSeeder::class,
            RolePermissionSeeder::class,
            ApprovalSettingSeeder::class,
            CabangPerusahaanSeeder::class,
            PerumahanSeeder::class,
            UserSeeder::class,
            UserPettyCashSeeder::class,

            // Data operasional dasar yang dibutuhkan transaksi penjualan.
            GudangSeeder::class,
            BarangMaterialSeeder::class,
            HppReferenceSeeder::class,
            OperationalSettingSeeder::class,
            MarketingLeadSourceSeeder::class,
            CostumerSeeder::class,
            MarketingTemplateSeeder::class,
            ChartOfAccountSeeder::class,
            DokumenCostumerSeeder::class,

            // Master metode pembayaran harus tersedia sebelum SPR dibuat.
            MasterBankSeeder::class,
            BankKreditSeeder::class,
            BankCreditMasterSeeder::class,
            SalesPaymentMasterSeeder::class,

            // Unit tersedia untuk pengujian manual mulai dari Reservasi.
            // SPR sengaja tidak di-seed agar seluruh alur dapat diuji dari awal.
            DetailRumahSeeder::class,

            // Data pendukung modul lain.
            TipePostSeeder::class,
            KelompokHppSeeder::class,
            InventoryHeavyEquipmentSeeder::class,
        ]);
    }
}
