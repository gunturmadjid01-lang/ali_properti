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
            PropertyAreaRoleSeeder::class,
            RolePermissionSeeder::class,
            CabangPerusahaanSeeder::class,
            UserSeeder::class,
            GudangSeeder::class,
            BarangMaterialSeeder::class,
            HppReferenceSeeder::class,
            OperationalSettingSeeder::class,
            MarketingLeadSourceSeeder::class,
            MarketingTemplateSeeder::class,
            ChartOfAccountSeeder::class,
            DokumenCostumerSeeder::class,
            MasterBankSeeder::class,
            BankKreditSeeder::class,
            TipePostSeeder::class,
            KelompokHppSeeder::class,
            InventoryHeavyEquipmentSeeder::class,
        ]);
    }
}
