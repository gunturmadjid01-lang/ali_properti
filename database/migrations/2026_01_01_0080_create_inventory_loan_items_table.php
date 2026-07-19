<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `inventory_loan_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `inventory_loan_id` bigint(20) unsigned NOT NULL,
  `inventory_item_id` bigint(20) unsigned NOT NULL,
  `office_asset_id` bigint(20) unsigned DEFAULT NULL,
  `quantity` int(10) unsigned NOT NULL DEFAULT 1,
  `condition_out` varchar(255) NOT NULL DEFAULT 'good',
  `returned_quantity` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `inventory_loan_items_inventory_loan_id_foreign` (`inventory_loan_id`),
  KEY `inventory_loan_items_inventory_item_id_foreign` (`inventory_item_id`),
  KEY `inventory_loan_items_office_asset_id_foreign` (`office_asset_id`),
  CONSTRAINT `inventory_loan_items_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`),
  CONSTRAINT `inventory_loan_items_inventory_loan_id_foreign` FOREIGN KEY (`inventory_loan_id`) REFERENCES `inventory_loans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_loan_items_office_asset_id_foreign` FOREIGN KEY (`office_asset_id`) REFERENCES `office_assets` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `inventory_loan_items`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
