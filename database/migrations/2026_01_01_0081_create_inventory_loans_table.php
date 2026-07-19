<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `inventory_loans` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `transaction_no` varchar(255) NOT NULL,
  `transaction_type` varchar(255) NOT NULL DEFAULT 'loan',
  `date` date NOT NULL,
  `borrower` varchar(255) NOT NULL,
  `taken_by_name` varchar(255) DEFAULT NULL,
  `taken_by_phone` varchar(255) DEFAULT NULL,
  `handed_over_by` bigint(20) unsigned DEFAULT NULL,
  `handed_over_at` timestamp NULL DEFAULT NULL,
  `division` varchar(255) DEFAULT NULL,
  `inventory_division_id` bigint(20) unsigned DEFAULT NULL,
  `inventory_location_id` bigint(20) unsigned DEFAULT NULL,
  `source_location_id` bigint(20) unsigned DEFAULT NULL,
  `perumahan_id` bigint(20) unsigned DEFAULT NULL,
  `detail_rumah_id` bigint(20) unsigned DEFAULT NULL,
  `block` varchar(255) DEFAULT NULL,
  `unit_house` varchar(255) DEFAULT NULL,
  `planned_return_date` date DEFAULT NULL,
  `purpose` text NOT NULL,
  `notes` text DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'borrowed',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inventory_loans_transaction_no_unique` (`transaction_no`),
  KEY `inventory_loans_inventory_location_id_foreign` (`inventory_location_id`),
  KEY `inventory_loans_perumahan_id_foreign` (`perumahan_id`),
  KEY `inventory_loans_created_by_foreign` (`created_by`),
  KEY `inventory_loans_updated_by_foreign` (`updated_by`),
  KEY `inventory_loans_handed_over_by_foreign` (`handed_over_by`),
  KEY `inventory_loans_source_location_id_foreign` (`source_location_id`),
  KEY `inventory_loans_detail_rumah_id_foreign` (`detail_rumah_id`),
  KEY `inventory_loans_inventory_division_id_foreign` (`inventory_division_id`),
  CONSTRAINT `inventory_loans_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_loans_detail_rumah_id_foreign` FOREIGN KEY (`detail_rumah_id`) REFERENCES `detail_rumahs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_loans_handed_over_by_foreign` FOREIGN KEY (`handed_over_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_loans_inventory_division_id_foreign` FOREIGN KEY (`inventory_division_id`) REFERENCES `inventory_divisions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_loans_inventory_location_id_foreign` FOREIGN KEY (`inventory_location_id`) REFERENCES `inventory_locations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_loans_perumahan_id_foreign` FOREIGN KEY (`perumahan_id`) REFERENCES `perumahans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_loans_source_location_id_foreign` FOREIGN KEY (`source_location_id`) REFERENCES `inventory_locations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_loans_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `inventory_loans`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
