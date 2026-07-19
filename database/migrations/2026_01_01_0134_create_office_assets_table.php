<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `office_assets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `inventory_item_id` bigint(20) unsigned NOT NULL,
  `kode_aset` varchar(255) NOT NULL,
  `nomor_seri` varchar(255) NOT NULL,
  `inventory_location_id` bigint(20) unsigned DEFAULT NULL,
  `current_user_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'available',
  `condition` varchar(255) NOT NULL DEFAULT 'good',
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `office_assets_kode_aset_unique` (`kode_aset`),
  UNIQUE KEY `office_assets_nomor_seri_unique` (`nomor_seri`),
  KEY `office_assets_inventory_item_id_foreign` (`inventory_item_id`),
  KEY `office_assets_inventory_location_id_foreign` (`inventory_location_id`),
  KEY `office_assets_current_user_id_foreign` (`current_user_id`),
  KEY `office_assets_created_by_foreign` (`created_by`),
  KEY `office_assets_updated_by_foreign` (`updated_by`),
  CONSTRAINT `office_assets_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `office_assets_current_user_id_foreign` FOREIGN KEY (`current_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `office_assets_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `office_assets_inventory_location_id_foreign` FOREIGN KEY (`inventory_location_id`) REFERENCES `inventory_locations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `office_assets_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `office_assets`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
