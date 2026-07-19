<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `heavy_component_replacements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `transaction_no` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `heavy_equipment_id` bigint(20) unsigned NOT NULL,
  `old_component_id` bigint(20) unsigned DEFAULT NULL,
  `new_component_id` bigint(20) unsigned NOT NULL,
  `hour_meter` decimal(12,2) NOT NULL,
  `reason` text NOT NULL,
  `operator_id` bigint(20) unsigned DEFAULT NULL,
  `technician` varchar(255) DEFAULT NULL,
  `old_component_condition` varchar(255) DEFAULT NULL,
  `old_component_status` varchar(255) NOT NULL DEFAULT 'available',
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `heavy_component_replacements_transaction_no_unique` (`transaction_no`),
  KEY `heavy_component_replacements_heavy_equipment_id_foreign` (`heavy_equipment_id`),
  KEY `heavy_component_replacements_old_component_id_foreign` (`old_component_id`),
  KEY `heavy_component_replacements_new_component_id_foreign` (`new_component_id`),
  KEY `heavy_component_replacements_operator_id_foreign` (`operator_id`),
  KEY `heavy_component_replacements_created_by_foreign` (`created_by`),
  KEY `heavy_component_replacements_updated_by_foreign` (`updated_by`),
  CONSTRAINT `heavy_component_replacements_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `heavy_component_replacements_heavy_equipment_id_foreign` FOREIGN KEY (`heavy_equipment_id`) REFERENCES `heavy_equipments` (`id`),
  CONSTRAINT `heavy_component_replacements_new_component_id_foreign` FOREIGN KEY (`new_component_id`) REFERENCES `heavy_equipment_components` (`id`),
  CONSTRAINT `heavy_component_replacements_old_component_id_foreign` FOREIGN KEY (`old_component_id`) REFERENCES `heavy_equipment_components` (`id`) ON DELETE SET NULL,
  CONSTRAINT `heavy_component_replacements_operator_id_foreign` FOREIGN KEY (`operator_id`) REFERENCES `heavy_equipment_operators` (`id`) ON DELETE SET NULL,
  CONSTRAINT `heavy_component_replacements_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `heavy_component_replacements`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
