<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

class CurrentDatabaseSnapshotSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/data/current_database_snapshot.php');
        if (! File::exists($path)) {
            throw new RuntimeException('Data snapshot belum dibuat. Jalankan php artisan db:snapshot-seeder.');
        }

        /** @var array<string, array<int, array<string, mixed>>> $snapshot */
        $snapshot = require $path;
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($snapshot as $table => $rows) {
                foreach (array_chunk($rows, 250) as $chunk) {
                    DB::table($table)->insertOrIgnore($chunk);
                }
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }
}
