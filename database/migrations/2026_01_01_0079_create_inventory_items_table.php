<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `inventory_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `inventory_category_id` bigint(20) unsigned NOT NULL,
  `brand` varchar(255) DEFAULT NULL,
  `model` varchar(255) DEFAULT NULL,
  `unit` varchar(255) NOT NULL DEFAULT 'Unit',
  `photo` varchar(255) DEFAULT NULL,
  `minimum_stock` int(10) unsigned NOT NULL DEFAULT 0,
  `inventory_type` varchar(255) NOT NULL DEFAULT 'quantity',
  `total_stock` int(10) unsigned NOT NULL DEFAULT 0,
  `available_stock` int(10) unsigned NOT NULL DEFAULT 0,
  `borrowed_stock` int(10) unsigned NOT NULL DEFAULT 0,
  `damaged_stock` int(10) unsigned NOT NULL DEFAULT 0,
  `lost_stock` int(10) unsigned NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inventory_items_code_unique` (`code`),
  KEY `inventory_items_inventory_category_id_foreign` (`inventory_category_id`),
  KEY `inventory_items_created_by_foreign` (`created_by`),
  KEY `inventory_items_updated_by_foreign` (`updated_by`),
  CONSTRAINT `inventory_items_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_items_inventory_category_id_foreign` FOREIGN KEY (`inventory_category_id`) REFERENCES `inventory_categories` (`id`),
  CONSTRAINT `inventory_items_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `inventory_items`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
