<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `barang_materials` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `kode_barang` varchar(255) NOT NULL,
  `nama_barang` varchar(255) NOT NULL,
  `material_type_id` bigint(20) unsigned DEFAULT NULL,
  `material_brand_id` bigint(20) unsigned DEFAULT NULL,
  `base_unit_id` bigint(20) unsigned DEFAULT NULL,
  `kategori_material` varchar(255) DEFAULT NULL,
  `jenis_material` varchar(255) DEFAULT NULL,
  `merk_material` varchar(255) DEFAULT NULL,
  `satuan` varchar(255) NOT NULL,
  `harga_hpp` decimal(16,2) NOT NULL DEFAULT 0.00,
  `stok_minimum` decimal(16,2) NOT NULL DEFAULT 0.00,
  `catatan` text DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'aktif',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `record_status` varchar(255) NOT NULL DEFAULT 'draft',
  `locked_at` timestamp NULL DEFAULT NULL,
  `locked_by` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `barang_materials_kode_barang_unique` (`kode_barang`),
  KEY `barang_materials_locked_by_foreign` (`locked_by`),
  KEY `barang_materials_created_by_foreign` (`created_by`),
  KEY `barang_materials_updated_by_foreign` (`updated_by`),
  KEY `barang_materials_material_type_id_foreign` (`material_type_id`),
  KEY `barang_materials_material_brand_id_foreign` (`material_brand_id`),
  KEY `barang_materials_base_unit_id_foreign` (`base_unit_id`),
  CONSTRAINT `barang_materials_base_unit_id_foreign` FOREIGN KEY (`base_unit_id`) REFERENCES `material_units` (`id`) ON DELETE SET NULL,
  CONSTRAINT `barang_materials_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `barang_materials_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `barang_materials_material_brand_id_foreign` FOREIGN KEY (`material_brand_id`) REFERENCES `material_brands` (`id`) ON DELETE SET NULL,
  CONSTRAINT `barang_materials_material_type_id_foreign` FOREIGN KEY (`material_type_id`) REFERENCES `material_types` (`id`) ON DELETE SET NULL,
  CONSTRAINT `barang_materials_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `barang_materials`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
