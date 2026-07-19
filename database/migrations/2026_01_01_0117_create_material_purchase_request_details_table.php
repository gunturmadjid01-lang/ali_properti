<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `material_purchase_request_details` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `material_purchase_request_id` bigint(20) unsigned NOT NULL,
  `barang_material_id` bigint(20) unsigned NOT NULL,
  `qty` decimal(16,2) NOT NULL,
  `satuan` varchar(255) DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mpr_details_request_fk` (`material_purchase_request_id`),
  KEY `material_purchase_request_details_barang_material_id_foreign` (`barang_material_id`),
  KEY `material_purchase_request_details_created_by_foreign` (`created_by`),
  KEY `material_purchase_request_details_updated_by_foreign` (`updated_by`),
  CONSTRAINT `material_purchase_request_details_barang_material_id_foreign` FOREIGN KEY (`barang_material_id`) REFERENCES `barang_materials` (`id`),
  CONSTRAINT `material_purchase_request_details_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_purchase_request_details_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mpr_details_request_fk` FOREIGN KEY (`material_purchase_request_id`) REFERENCES `material_purchase_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `material_purchase_request_details`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
