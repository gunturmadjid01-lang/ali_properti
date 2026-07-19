<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `detail_perumahan_hpps` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `perumahan_hpp_id` bigint(20) unsigned NOT NULL,
  `kelompok_hpp_id` bigint(20) unsigned NOT NULL,
  `tahapan_pembangunan_id` bigint(20) unsigned DEFAULT NULL,
  `nama_pekerjaan` varchar(255) DEFAULT NULL,
  `urutan` int(10) unsigned NOT NULL DEFAULT 0,
  `barang_material_id` bigint(20) unsigned DEFAULT NULL,
  `volume` float NOT NULL,
  `satuan` varchar(255) NOT NULL,
  `harga_satuan` double NOT NULL,
  `jumlah_rab` double NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `detail_perumahan_hpps_perumahan_hpp_id_foreign` (`perumahan_hpp_id`),
  KEY `detail_perumahan_hpps_kelompok_hpp_id_foreign` (`kelompok_hpp_id`),
  KEY `detail_perumahan_hpps_barang_material_id_foreign` (`barang_material_id`),
  KEY `detail_perumahan_hpps_created_by_foreign` (`created_by`),
  KEY `detail_perumahan_hpps_updated_by_foreign` (`updated_by`),
  KEY `detail_perumahan_hpps_tahapan_pembangunan_id_foreign` (`tahapan_pembangunan_id`),
  CONSTRAINT `detail_perumahan_hpps_barang_material_id_foreign` FOREIGN KEY (`barang_material_id`) REFERENCES `barang_materials` (`id`) ON DELETE SET NULL,
  CONSTRAINT `detail_perumahan_hpps_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `detail_perumahan_hpps_kelompok_hpp_id_foreign` FOREIGN KEY (`kelompok_hpp_id`) REFERENCES `kelompok_hpps` (`id`) ON DELETE CASCADE,
  CONSTRAINT `detail_perumahan_hpps_perumahan_hpp_id_foreign` FOREIGN KEY (`perumahan_hpp_id`) REFERENCES `perumahan_hpps` (`id`) ON DELETE CASCADE,
  CONSTRAINT `detail_perumahan_hpps_tahapan_pembangunan_id_foreign` FOREIGN KEY (`tahapan_pembangunan_id`) REFERENCES `tahapan_pembangunans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `detail_perumahan_hpps_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `detail_perumahan_hpps`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
