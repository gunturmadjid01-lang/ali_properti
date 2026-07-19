<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `stok_materials` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `gudang_id` bigint(20) unsigned DEFAULT NULL,
  `barang_material_id` bigint(20) unsigned NOT NULL,
  `cabang_id` bigint(20) unsigned DEFAULT NULL,
  `qty` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `stok_materials_barang_material_id_cabang_id_unique` (`barang_material_id`,`cabang_id`),
  KEY `stok_materials_cabang_id_foreign` (`cabang_id`),
  KEY `stok_materials_gudang_id_foreign` (`gudang_id`),
  KEY `stok_materials_created_by_foreign` (`created_by`),
  KEY `stok_materials_updated_by_foreign` (`updated_by`),
  CONSTRAINT `stok_materials_barang_material_id_foreign` FOREIGN KEY (`barang_material_id`) REFERENCES `barang_materials` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stok_materials_cabang_id_foreign` FOREIGN KEY (`cabang_id`) REFERENCES `cabang_perusahaans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `stok_materials_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `stok_materials_gudang_id_foreign` FOREIGN KEY (`gudang_id`) REFERENCES `gudangs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `stok_materials_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `stok_materials`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
