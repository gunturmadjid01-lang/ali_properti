<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `inventory_returns` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `return_no` varchar(255) NOT NULL,
  `inventory_loan_id` bigint(20) unsigned NOT NULL,
  `date` date NOT NULL,
  `return_location_id` bigint(20) unsigned DEFAULT NULL,
  `received_by` bigint(20) unsigned DEFAULT NULL,
  `received_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inventory_returns_return_no_unique` (`return_no`),
  KEY `inventory_returns_inventory_loan_id_foreign` (`inventory_loan_id`),
  KEY `inventory_returns_created_by_foreign` (`created_by`),
  KEY `inventory_returns_updated_by_foreign` (`updated_by`),
  KEY `inventory_returns_return_location_id_foreign` (`return_location_id`),
  KEY `inventory_returns_received_by_foreign` (`received_by`),
  CONSTRAINT `inventory_returns_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_returns_inventory_loan_id_foreign` FOREIGN KEY (`inventory_loan_id`) REFERENCES `inventory_loans` (`id`),
  CONSTRAINT `inventory_returns_received_by_foreign` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_returns_return_location_id_foreign` FOREIGN KEY (`return_location_id`) REFERENCES `inventory_locations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_returns_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `inventory_returns`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
