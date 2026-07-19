<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `heavy_equipment_fuelings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `heavy_equipment_id` bigint(20) unsigned NOT NULL,
  `fuel_type` varchar(255) NOT NULL,
  `liters` decimal(12,2) NOT NULL,
  `price_per_liter` decimal(18,2) NOT NULL,
  `total_cost` decimal(18,2) NOT NULL,
  `hour_meter` decimal(12,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `heavy_equipment_fuelings_heavy_equipment_id_foreign` (`heavy_equipment_id`),
  KEY `heavy_equipment_fuelings_created_by_foreign` (`created_by`),
  KEY `heavy_equipment_fuelings_updated_by_foreign` (`updated_by`),
  CONSTRAINT `heavy_equipment_fuelings_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `heavy_equipment_fuelings_heavy_equipment_id_foreign` FOREIGN KEY (`heavy_equipment_id`) REFERENCES `heavy_equipments` (`id`),
  CONSTRAINT `heavy_equipment_fuelings_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `heavy_equipment_fuelings`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
