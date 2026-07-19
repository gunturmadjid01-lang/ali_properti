<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `heavy_equipment_maintenances` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `maintenance_no` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `heavy_equipment_id` bigint(20) unsigned NOT NULL,
  `maintenance_type` varchar(255) NOT NULL,
  `workshop` varchar(255) DEFAULT NULL,
  `cost` decimal(18,2) NOT NULL DEFAULT 0.00,
  `next_schedule` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'scheduled',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `heavy_equipment_maintenances_maintenance_no_unique` (`maintenance_no`),
  KEY `heavy_equipment_maintenances_heavy_equipment_id_foreign` (`heavy_equipment_id`),
  KEY `heavy_equipment_maintenances_created_by_foreign` (`created_by`),
  KEY `heavy_equipment_maintenances_updated_by_foreign` (`updated_by`),
  CONSTRAINT `heavy_equipment_maintenances_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `heavy_equipment_maintenances_heavy_equipment_id_foreign` FOREIGN KEY (`heavy_equipment_id`) REFERENCES `heavy_equipments` (`id`),
  CONSTRAINT `heavy_equipment_maintenances_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `heavy_equipment_maintenances`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
