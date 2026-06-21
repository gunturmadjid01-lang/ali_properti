<?php

namespace Database\Seeders;

use App\Models\OperationalSetting;
use Illuminate\Database\Seeder;

class OperationalSettingSeeder extends Seeder
{
    public function run(): void
    {
        OperationalSetting::query()->updateOrCreate(
            ['key' => 'manager_can_release_purchase_fund'],
            ['value' => '0'],
        );
    }
}
