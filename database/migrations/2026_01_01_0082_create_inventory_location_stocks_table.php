<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `inventory_location_stocks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `inventory_item_id` bigint(20) unsigned NOT NULL,
  `inventory_location_id` bigint(20) unsigned NOT NULL,
  `total_stock` int(10) unsigned NOT NULL DEFAULT 0,
  `available_stock` int(10) unsigned NOT NULL DEFAULT 0,
  `borrowed_stock` int(10) unsigned NOT NULL DEFAULT 0,
  `damaged_stock` int(10) unsigned NOT NULL DEFAULT 0,
  `lost_stock` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inventory_item_location_unique` (`inventory_item_id`,`inventory_location_id`),
  KEY `inventory_location_stocks_inventory_location_id_foreign` (`inventory_location_id`),
  CONSTRAINT `inventory_location_stocks_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_location_stocks_inventory_location_id_foreign` FOREIGN KEY (`inventory_location_id`) REFERENCES `inventory_locations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `inventory_location_stocks`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
