<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `inventory_transfers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `transaction_no` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `inventory_item_id` bigint(20) unsigned NOT NULL,
  `office_asset_id` bigint(20) unsigned DEFAULT NULL,
  `quantity` int(10) unsigned NOT NULL DEFAULT 1,
  `from_location_id` bigint(20) unsigned DEFAULT NULL,
  `to_location_id` bigint(20) unsigned NOT NULL,
  `reason` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inventory_transfers_transaction_no_unique` (`transaction_no`),
  KEY `inventory_transfers_inventory_item_id_foreign` (`inventory_item_id`),
  KEY `inventory_transfers_office_asset_id_foreign` (`office_asset_id`),
  KEY `inventory_transfers_from_location_id_foreign` (`from_location_id`),
  KEY `inventory_transfers_to_location_id_foreign` (`to_location_id`),
  KEY `inventory_transfers_created_by_foreign` (`created_by`),
  KEY `inventory_transfers_updated_by_foreign` (`updated_by`),
  CONSTRAINT `inventory_transfers_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_transfers_from_location_id_foreign` FOREIGN KEY (`from_location_id`) REFERENCES `inventory_locations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_transfers_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`),
  CONSTRAINT `inventory_transfers_office_asset_id_foreign` FOREIGN KEY (`office_asset_id`) REFERENCES `office_assets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_transfers_to_location_id_foreign` FOREIGN KEY (`to_location_id`) REFERENCES `inventory_locations` (`id`),
  CONSTRAINT `inventory_transfers_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `inventory_transfers`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
