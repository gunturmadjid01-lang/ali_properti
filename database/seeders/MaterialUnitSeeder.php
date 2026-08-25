<?php

namespace Database\Seeders;

use App\Models\MaterialUnit;
use Illuminate\Database\Seeder;

class MaterialUnitSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['code' => 'STN-001', 'name' => 'Pcs', 'symbol' => 'PCS'],
            ['code' => 'STN-002', 'name' => 'Pak', 'symbol' => 'PAK'],
            ['code' => 'STN-003', 'name' => 'Dus', 'symbol' => 'DUS'],
            ['code' => 'STN-004', 'name' => 'Sak', 'symbol' => 'SAK'],
            ['code' => 'STN-005', 'name' => 'Batang', 'symbol' => 'BTG'],
            ['code' => 'STN-006', 'name' => 'Meter', 'symbol' => 'M'],
            ['code' => 'STN-007', 'name' => 'Meter Persegi', 'symbol' => 'M2'],
            ['code' => 'STN-008', 'name' => 'Meter Kubik', 'symbol' => 'M3'],
            ['code' => 'STN-009', 'name' => 'Kilogram', 'symbol' => 'KG'],
            ['code' => 'STN-010', 'name' => 'Liter', 'symbol' => 'LTR'],
            ['code' => 'STN-011', 'name' => 'Roll', 'symbol' => 'ROLL'],
            ['code' => 'STN-012', 'name' => 'Set', 'symbol' => 'SET'],
        ];

        foreach ($units as $unit) {
            MaterialUnit::query()->updateOrCreate(
                ['code' => $unit['code']],
                [
                    ...$unit,
                    'description' => 'Satuan standar material',
                    'status' => 'aktif',
                ],
            );
        }
    }
}
