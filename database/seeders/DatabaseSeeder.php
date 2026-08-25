<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (File::exists(database_path('seeders/data/current_database_snapshot.php'))) {
            $this->call(CurrentDatabaseSnapshotSeeder::class);

            return;
        }

        $this->call([
            // Fondasi akses: hanya super_admin yang menerima permission.
            PropertyAreaRoleSeeder::class,
            RolePermissionSeeder::class,

            // Data dasar yang dipertahankan untuk instalasi bersih.
            CabangPerusahaanSeeder::class,
            PerumahanSeeder::class,
            UserSeeder::class,
            MaterialUnitSeeder::class,
        ]);
    }
}
