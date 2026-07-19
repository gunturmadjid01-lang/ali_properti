<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `heavy_equipment_usages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `transaction_no` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `heavy_equipment_id` bigint(20) unsigned NOT NULL,
  `operator_id` bigint(20) unsigned NOT NULL,
  `perumahan_id` bigint(20) unsigned DEFAULT NULL,
  `detail_rumah_id` bigint(20) unsigned DEFAULT NULL,
  `project` varchar(255) DEFAULT NULL,
  `hour_meter_start` decimal(12,2) NOT NULL,
  `hour_meter_end` decimal(12,2) DEFAULT NULL,
  `duration_hours` decimal(10,2) NOT NULL DEFAULT 0.00,
  `description` text DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'in_use',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `heavy_equipment_usages_transaction_no_unique` (`transaction_no`),
  KEY `heavy_equipment_usages_heavy_equipment_id_foreign` (`heavy_equipment_id`),
  KEY `heavy_equipment_usages_operator_id_foreign` (`operator_id`),
  KEY `heavy_equipment_usages_created_by_foreign` (`created_by`),
  KEY `heavy_equipment_usages_updated_by_foreign` (`updated_by`),
  KEY `heavy_equipment_usages_perumahan_id_foreign` (`perumahan_id`),
  KEY `heavy_equipment_usages_detail_rumah_id_foreign` (`detail_rumah_id`),
  CONSTRAINT `heavy_equipment_usages_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `heavy_equipment_usages_detail_rumah_id_foreign` FOREIGN KEY (`detail_rumah_id`) REFERENCES `detail_rumahs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `heavy_equipment_usages_heavy_equipment_id_foreign` FOREIGN KEY (`heavy_equipment_id`) REFERENCES `heavy_equipments` (`id`),
  CONSTRAINT `heavy_equipment_usages_operator_id_foreign` FOREIGN KEY (`operator_id`) REFERENCES `heavy_equipment_operators` (`id`),
  CONSTRAINT `heavy_equipment_usages_perumahan_id_foreign` FOREIGN KEY (`perumahan_id`) REFERENCES `perumahans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `heavy_equipment_usages_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `heavy_equipment_usages`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
