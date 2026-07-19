<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `inventory_locations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL DEFAULT 'other',
  `owner_type` enum('company','branch','housing') NOT NULL DEFAULT 'company',
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `perumahan_id` bigint(20) unsigned DEFAULT NULL,
  `address` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inventory_locations_code_unique` (`code`),
  KEY `inventory_locations_created_by_foreign` (`created_by`),
  KEY `inventory_locations_updated_by_foreign` (`updated_by`),
  KEY `inventory_locations_branch_id_foreign` (`branch_id`),
  KEY `inventory_locations_perumahan_id_foreign` (`perumahan_id`),
  CONSTRAINT `inventory_locations_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `cabang_perusahaans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_locations_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_locations_perumahan_id_foreign` FOREIGN KEY (`perumahan_id`) REFERENCES `perumahans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_locations_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `inventory_locations`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
