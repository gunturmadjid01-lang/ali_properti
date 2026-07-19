<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `inventory_movements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `occurred_at` datetime NOT NULL,
  `movement_type` varchar(255) NOT NULL,
  `reference_type` varchar(255) NOT NULL,
  `reference_id` bigint(20) unsigned NOT NULL,
  `reference_no` varchar(255) DEFAULT NULL,
  `inventory_item_id` bigint(20) unsigned NOT NULL,
  `office_asset_id` bigint(20) unsigned DEFAULT NULL,
  `from_location_id` bigint(20) unsigned DEFAULT NULL,
  `to_location_id` bigint(20) unsigned DEFAULT NULL,
  `quantity` int(10) unsigned NOT NULL,
  `condition_bucket` varchar(255) NOT NULL DEFAULT 'available',
  `performed_by` bigint(20) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `inventory_movements_office_asset_id_foreign` (`office_asset_id`),
  KEY `inventory_movements_from_location_id_foreign` (`from_location_id`),
  KEY `inventory_movements_to_location_id_foreign` (`to_location_id`),
  KEY `inventory_movements_performed_by_foreign` (`performed_by`),
  KEY `inventory_movements_inventory_item_id_occurred_at_index` (`inventory_item_id`,`occurred_at`),
  KEY `inventory_movements_reference_type_reference_id_index` (`reference_type`,`reference_id`),
  CONSTRAINT `inventory_movements_from_location_id_foreign` FOREIGN KEY (`from_location_id`) REFERENCES `inventory_locations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_movements_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`),
  CONSTRAINT `inventory_movements_office_asset_id_foreign` FOREIGN KEY (`office_asset_id`) REFERENCES `office_assets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_movements_performed_by_foreign` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_movements_to_location_id_foreign` FOREIGN KEY (`to_location_id`) REFERENCES `inventory_locations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `inventory_movements`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
