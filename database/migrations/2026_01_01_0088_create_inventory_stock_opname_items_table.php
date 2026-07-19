<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `inventory_stock_opname_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `inventory_stock_opname_id` bigint(20) unsigned NOT NULL,
  `inventory_item_id` bigint(20) unsigned NOT NULL,
  `system_quantity` int(10) unsigned NOT NULL,
  `physical_quantity` int(10) unsigned NOT NULL,
  `difference` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `inventory_stock_opname_items_inventory_stock_opname_id_foreign` (`inventory_stock_opname_id`),
  KEY `inventory_stock_opname_items_inventory_item_id_foreign` (`inventory_item_id`),
  CONSTRAINT `inventory_stock_opname_items_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`),
  CONSTRAINT `inventory_stock_opname_items_inventory_stock_opname_id_foreign` FOREIGN KEY (`inventory_stock_opname_id`) REFERENCES `inventory_stock_opnames` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `inventory_stock_opname_items`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
