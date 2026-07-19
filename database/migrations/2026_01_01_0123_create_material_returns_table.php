<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `material_returns` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `kode_pengembalian` varchar(255) NOT NULL,
  `tanggal` date NOT NULL,
  `gudang_id` bigint(20) unsigned NOT NULL,
  `perumahan_id` bigint(20) unsigned NOT NULL,
  `detail_rumah_id` bigint(20) unsigned DEFAULT NULL,
  `tahapan_pembangunan_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'diajukan',
  `keterangan` text DEFAULT NULL,
  `receive_note` text DEFAULT NULL,
  `received_by` bigint(20) unsigned DEFAULT NULL,
  `received_at` timestamp NULL DEFAULT NULL,
  `transaksi_logistik_id` bigint(20) unsigned DEFAULT NULL,
  `record_status` varchar(255) NOT NULL DEFAULT 'draft',
  `locked_at` timestamp NULL DEFAULT NULL,
  `locked_by` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `material_returns_kode_pengembalian_unique` (`kode_pengembalian`),
  KEY `material_returns_gudang_id_foreign` (`gudang_id`),
  KEY `material_returns_perumahan_id_foreign` (`perumahan_id`),
  KEY `material_returns_detail_rumah_id_foreign` (`detail_rumah_id`),
  KEY `material_returns_tahapan_pembangunan_id_foreign` (`tahapan_pembangunan_id`),
  KEY `material_returns_received_by_foreign` (`received_by`),
  KEY `material_returns_transaksi_logistik_id_foreign` (`transaksi_logistik_id`),
  KEY `material_returns_locked_by_foreign` (`locked_by`),
  KEY `material_returns_created_by_foreign` (`created_by`),
  KEY `material_returns_updated_by_foreign` (`updated_by`),
  CONSTRAINT `material_returns_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_returns_detail_rumah_id_foreign` FOREIGN KEY (`detail_rumah_id`) REFERENCES `detail_rumahs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `material_returns_gudang_id_foreign` FOREIGN KEY (`gudang_id`) REFERENCES `gudangs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `material_returns_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_returns_perumahan_id_foreign` FOREIGN KEY (`perumahan_id`) REFERENCES `perumahans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `material_returns_received_by_foreign` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_returns_tahapan_pembangunan_id_foreign` FOREIGN KEY (`tahapan_pembangunan_id`) REFERENCES `tahapan_pembangunans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_returns_transaksi_logistik_id_foreign` FOREIGN KEY (`transaksi_logistik_id`) REFERENCES `transaksi_logistiks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_returns_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `material_returns`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
