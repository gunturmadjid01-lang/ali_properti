<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `site_material_stocks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `gudang_id` bigint(20) unsigned NOT NULL,
  `perumahan_id` bigint(20) unsigned NOT NULL,
  `detail_rumah_id` bigint(20) unsigned DEFAULT NULL,
  `tahapan_pembangunan_id` bigint(20) unsigned DEFAULT NULL,
  `kelompok_hpp_id` bigint(20) unsigned DEFAULT NULL,
  `barang_material_id` bigint(20) unsigned NOT NULL,
  `qty_received` decimal(16,2) NOT NULL DEFAULT 0.00,
  `qty_used` decimal(16,2) NOT NULL DEFAULT 0.00,
  `qty_returned` decimal(16,2) NOT NULL DEFAULT 0.00,
  `qty_reserved_return` decimal(16,2) NOT NULL DEFAULT 0.00,
  `qty_available` decimal(16,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `site_material_stock_unique` (`gudang_id`,`perumahan_id`,`detail_rumah_id`,`tahapan_pembangunan_id`,`barang_material_id`),
  KEY `site_material_stocks_perumahan_id_foreign` (`perumahan_id`),
  KEY `site_material_stocks_detail_rumah_id_foreign` (`detail_rumah_id`),
  KEY `site_material_stocks_tahapan_pembangunan_id_foreign` (`tahapan_pembangunan_id`),
  KEY `site_material_stocks_barang_material_id_foreign` (`barang_material_id`),
  KEY `site_material_stocks_kelompok_hpp_id_foreign` (`kelompok_hpp_id`),
  CONSTRAINT `site_material_stocks_barang_material_id_foreign` FOREIGN KEY (`barang_material_id`) REFERENCES `barang_materials` (`id`) ON DELETE CASCADE,
  CONSTRAINT `site_material_stocks_detail_rumah_id_foreign` FOREIGN KEY (`detail_rumah_id`) REFERENCES `detail_rumahs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `site_material_stocks_gudang_id_foreign` FOREIGN KEY (`gudang_id`) REFERENCES `gudangs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `site_material_stocks_kelompok_hpp_id_foreign` FOREIGN KEY (`kelompok_hpp_id`) REFERENCES `kelompok_hpps` (`id`) ON DELETE SET NULL,
  CONSTRAINT `site_material_stocks_perumahan_id_foreign` FOREIGN KEY (`perumahan_id`) REFERENCES `perumahans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `site_material_stocks_tahapan_pembangunan_id_foreign` FOREIGN KEY (`tahapan_pembangunan_id`) REFERENCES `tahapan_pembangunans` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `site_material_stocks`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
