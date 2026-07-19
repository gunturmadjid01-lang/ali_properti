<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `inventory_return_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `inventory_return_id` bigint(20) unsigned NOT NULL,
  `inventory_loan_item_id` bigint(20) unsigned NOT NULL,
  `quantity` int(10) unsigned NOT NULL,
  `good_quantity` int(10) unsigned NOT NULL DEFAULT 0,
  `condition_in` varchar(255) NOT NULL DEFAULT 'good',
  `outcome` varchar(255) NOT NULL DEFAULT 'complete_good',
  `is_complete` tinyint(1) NOT NULL DEFAULT 1,
  `damaged_quantity` int(10) unsigned NOT NULL DEFAULT 0,
  `lost_quantity` int(10) unsigned NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `responsible_person` varchar(255) DEFAULT NULL,
  `estimated_cost` decimal(18,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `inventory_return_items_inventory_return_id_foreign` (`inventory_return_id`),
  KEY `inventory_return_items_inventory_loan_item_id_foreign` (`inventory_loan_item_id`),
  CONSTRAINT `inventory_return_items_inventory_loan_item_id_foreign` FOREIGN KEY (`inventory_loan_item_id`) REFERENCES `inventory_loan_items` (`id`),
  CONSTRAINT `inventory_return_items_inventory_return_id_foreign` FOREIGN KEY (`inventory_return_id`) REFERENCES `inventory_returns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `inventory_return_items`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
