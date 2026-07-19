<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `gudangs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `kode_gudang` varchar(255) NOT NULL,
  `nama_gudang` varchar(255) NOT NULL,
  `cabang_id` bigint(20) unsigned DEFAULT NULL,
  `perumahan_id` bigint(20) unsigned DEFAULT NULL,
  `penanggung_jawab` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'aktif',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `record_status` varchar(255) NOT NULL DEFAULT 'draft',
  `locked_at` timestamp NULL DEFAULT NULL,
  `locked_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `gudangs_kode_gudang_unique` (`kode_gudang`),
  KEY `gudangs_cabang_id_foreign` (`cabang_id`),
  KEY `gudangs_perumahan_id_foreign` (`perumahan_id`),
  KEY `gudangs_created_by_foreign` (`created_by`),
  KEY `gudangs_updated_by_foreign` (`updated_by`),
  KEY `gudangs_locked_by_foreign` (`locked_by`),
  CONSTRAINT `gudangs_cabang_id_foreign` FOREIGN KEY (`cabang_id`) REFERENCES `cabang_perusahaans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `gudangs_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `gudangs_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `gudangs_perumahan_id_foreign` FOREIGN KEY (`perumahan_id`) REFERENCES `perumahans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `gudangs_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `gudangs`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
