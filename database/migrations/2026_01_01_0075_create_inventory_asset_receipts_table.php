<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `inventory_asset_receipts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `receipt_no` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `inventory_item_id` bigint(20) unsigned NOT NULL,
  `office_asset_id` bigint(20) unsigned DEFAULT NULL,
  `quantity` int(10) unsigned NOT NULL DEFAULT 1,
  `inventory_location_id` bigint(20) unsigned NOT NULL,
  `source` varchar(255) DEFAULT NULL,
  `reference_no` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `record_status` varchar(255) NOT NULL DEFAULT 'draft',
  `locked_at` timestamp NULL DEFAULT NULL,
  `locked_by` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inventory_asset_receipts_receipt_no_unique` (`receipt_no`),
  KEY `inventory_asset_receipts_inventory_item_id_foreign` (`inventory_item_id`),
  KEY `inventory_asset_receipts_office_asset_id_foreign` (`office_asset_id`),
  KEY `inventory_asset_receipts_inventory_location_id_foreign` (`inventory_location_id`),
  KEY `inventory_asset_receipts_locked_by_foreign` (`locked_by`),
  KEY `inventory_asset_receipts_created_by_foreign` (`created_by`),
  KEY `inventory_asset_receipts_updated_by_foreign` (`updated_by`),
  CONSTRAINT `inventory_asset_receipts_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_asset_receipts_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`),
  CONSTRAINT `inventory_asset_receipts_inventory_location_id_foreign` FOREIGN KEY (`inventory_location_id`) REFERENCES `inventory_locations` (`id`),
  CONSTRAINT `inventory_asset_receipts_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_asset_receipts_office_asset_id_foreign` FOREIGN KEY (`office_asset_id`) REFERENCES `office_assets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_asset_receipts_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `inventory_asset_receipts`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
