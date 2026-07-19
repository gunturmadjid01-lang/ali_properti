<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `transaksi_logistiks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `gudang_id` bigint(20) unsigned DEFAULT NULL,
  `kode_transaksi` varchar(255) NOT NULL,
  `tanggal` date NOT NULL,
  `jenis` varchar(255) NOT NULL,
  `perumahan_id` bigint(20) unsigned DEFAULT NULL,
  `detail_rumah_id` bigint(20) unsigned DEFAULT NULL,
  `tahapan_pembangunan_id` bigint(20) unsigned DEFAULT NULL,
  `kelompok_hpp_id` bigint(20) unsigned DEFAULT NULL,
  `total_nominal` decimal(16,2) NOT NULL DEFAULT 0.00,
  `keterangan` text DEFAULT NULL,
  `source_type` varchar(255) DEFAULT NULL,
  `source_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `transaksi_logistiks_kode_transaksi_unique` (`kode_transaksi`),
  KEY `transaksi_logistiks_perumahan_id_foreign` (`perumahan_id`),
  KEY `transaksi_logistiks_detail_rumah_id_foreign` (`detail_rumah_id`),
  KEY `transaksi_logistiks_tahapan_pembangunan_id_foreign` (`tahapan_pembangunan_id`),
  KEY `transaksi_logistiks_kelompok_hpp_id_foreign` (`kelompok_hpp_id`),
  KEY `transaksi_logistiks_user_id_foreign` (`user_id`),
  KEY `transaksi_logistiks_gudang_id_foreign` (`gudang_id`),
  KEY `transaksi_logistiks_created_by_foreign` (`created_by`),
  KEY `transaksi_logistiks_updated_by_foreign` (`updated_by`),
  KEY `transaksi_logistiks_source_type_source_id_index` (`source_type`,`source_id`),
  CONSTRAINT `transaksi_logistiks_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `transaksi_logistiks_detail_rumah_id_foreign` FOREIGN KEY (`detail_rumah_id`) REFERENCES `detail_rumahs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `transaksi_logistiks_gudang_id_foreign` FOREIGN KEY (`gudang_id`) REFERENCES `gudangs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `transaksi_logistiks_kelompok_hpp_id_foreign` FOREIGN KEY (`kelompok_hpp_id`) REFERENCES `kelompok_hpps` (`id`) ON DELETE SET NULL,
  CONSTRAINT `transaksi_logistiks_perumahan_id_foreign` FOREIGN KEY (`perumahan_id`) REFERENCES `perumahans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `transaksi_logistiks_tahapan_pembangunan_id_foreign` FOREIGN KEY (`tahapan_pembangunan_id`) REFERENCES `tahapan_pembangunans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `transaksi_logistiks_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `transaksi_logistiks_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `transaksi_logistiks`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
