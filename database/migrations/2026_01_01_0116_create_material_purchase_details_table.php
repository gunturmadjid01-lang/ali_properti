<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `material_purchase_details` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `material_purchase_id` bigint(20) unsigned NOT NULL,
  `barang_material_id` bigint(20) unsigned NOT NULL,
  `material_unit_id` bigint(20) unsigned DEFAULT NULL,
  `qty` decimal(18,6) NOT NULL,
  `qty_base` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `qty_diterima` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `qty_diterima_base` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `inspection_status` varchar(255) NOT NULL DEFAULT 'pending',
  `inspection_note` text DEFAULT NULL,
  `checked_by` bigint(20) unsigned DEFAULT NULL,
  `checked_at` timestamp NULL DEFAULT NULL,
  `satuan` varchar(255) NOT NULL,
  `conversion_to_base` decimal(18,6) NOT NULL DEFAULT 1.000000,
  `harga_satuan` decimal(16,2) NOT NULL,
  `harga_satuan_base` decimal(18,2) NOT NULL DEFAULT 0.00,
  `diskon` decimal(16,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(16,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `material_purchase_details_material_purchase_id_foreign` (`material_purchase_id`),
  KEY `material_purchase_details_barang_material_id_foreign` (`barang_material_id`),
  KEY `material_purchase_details_created_by_foreign` (`created_by`),
  KEY `material_purchase_details_updated_by_foreign` (`updated_by`),
  KEY `material_purchase_details_checked_by_foreign` (`checked_by`),
  KEY `material_purchase_details_material_unit_id_foreign` (`material_unit_id`),
  CONSTRAINT `material_purchase_details_barang_material_id_foreign` FOREIGN KEY (`barang_material_id`) REFERENCES `barang_materials` (`id`) ON DELETE CASCADE,
  CONSTRAINT `material_purchase_details_checked_by_foreign` FOREIGN KEY (`checked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_purchase_details_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_purchase_details_material_purchase_id_foreign` FOREIGN KEY (`material_purchase_id`) REFERENCES `material_purchases` (`id`) ON DELETE CASCADE,
  CONSTRAINT `material_purchase_details_material_unit_id_foreign` FOREIGN KEY (`material_unit_id`) REFERENCES `material_units` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_purchase_details_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `material_purchase_details`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
