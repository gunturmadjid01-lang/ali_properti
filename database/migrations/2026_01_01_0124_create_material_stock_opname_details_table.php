<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `material_stock_opname_details` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `material_stock_opname_id` bigint(20) unsigned NOT NULL,
  `barang_material_id` bigint(20) unsigned NOT NULL,
  `stok_sistem` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `fisik` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `physical_unit_counts` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`physical_unit_counts`)),
  `masuk` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `keluar` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `selisih` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `catatan` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `msod_opname_material_unique` (`material_stock_opname_id`,`barang_material_id`),
  KEY `material_stock_opname_details_barang_material_id_foreign` (`barang_material_id`),
  CONSTRAINT `material_stock_opname_details_barang_material_id_foreign` FOREIGN KEY (`barang_material_id`) REFERENCES `barang_materials` (`id`) ON DELETE CASCADE,
  CONSTRAINT `material_stock_opname_details_material_stock_opname_id_foreign` FOREIGN KEY (`material_stock_opname_id`) REFERENCES `material_stock_opnames` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `material_stock_opname_details`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
