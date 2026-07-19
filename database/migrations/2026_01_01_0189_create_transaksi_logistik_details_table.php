<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `transaksi_logistik_details` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `transaksi_logistik_id` bigint(20) unsigned NOT NULL,
  `barang_material_id` bigint(20) unsigned NOT NULL,
  `qty` decimal(18,6) NOT NULL,
  `input_qty` decimal(18,6) DEFAULT NULL,
  `input_unit_id` bigint(20) unsigned DEFAULT NULL,
  `input_satuan` varchar(255) DEFAULT NULL,
  `conversion_to_base` decimal(18,6) NOT NULL DEFAULT 1.000000,
  `satuan` varchar(255) NOT NULL,
  `harga_satuan` decimal(16,2) NOT NULL,
  `subtotal` decimal(16,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `transaksi_logistik_details_transaksi_logistik_id_foreign` (`transaksi_logistik_id`),
  KEY `transaksi_logistik_details_barang_material_id_foreign` (`barang_material_id`),
  KEY `transaksi_logistik_details_created_by_foreign` (`created_by`),
  KEY `transaksi_logistik_details_updated_by_foreign` (`updated_by`),
  KEY `transaksi_logistik_details_input_unit_id_foreign` (`input_unit_id`),
  CONSTRAINT `transaksi_logistik_details_barang_material_id_foreign` FOREIGN KEY (`barang_material_id`) REFERENCES `barang_materials` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transaksi_logistik_details_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `transaksi_logistik_details_input_unit_id_foreign` FOREIGN KEY (`input_unit_id`) REFERENCES `material_units` (`id`) ON DELETE SET NULL,
  CONSTRAINT `transaksi_logistik_details_transaksi_logistik_id_foreign` FOREIGN KEY (`transaksi_logistik_id`) REFERENCES `transaksi_logistiks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transaksi_logistik_details_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `transaksi_logistik_details`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
