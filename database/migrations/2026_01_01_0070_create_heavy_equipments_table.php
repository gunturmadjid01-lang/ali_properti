<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `heavy_equipments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `heavy_equipment_type_id` bigint(20) unsigned NOT NULL,
  `brand` varchar(255) DEFAULT NULL,
  `model` varchar(255) DEFAULT NULL,
  `year` smallint(5) unsigned DEFAULT NULL,
  `serial_no` varchar(255) NOT NULL,
  `license_plate` varchar(255) DEFAULT NULL,
  `current_hour_meter` decimal(12,2) NOT NULL DEFAULT 0.00,
  `ownership` varchar(255) NOT NULL DEFAULT 'company',
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `heavy_equipments_code_unique` (`code`),
  UNIQUE KEY `heavy_equipments_serial_no_unique` (`serial_no`),
  KEY `heavy_equipments_heavy_equipment_type_id_foreign` (`heavy_equipment_type_id`),
  KEY `heavy_equipments_created_by_foreign` (`created_by`),
  KEY `heavy_equipments_updated_by_foreign` (`updated_by`),
  CONSTRAINT `heavy_equipments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `heavy_equipments_heavy_equipment_type_id_foreign` FOREIGN KEY (`heavy_equipment_type_id`) REFERENCES `heavy_equipment_types` (`id`),
  CONSTRAINT `heavy_equipments_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `heavy_equipments`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
