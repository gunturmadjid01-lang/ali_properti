<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `work_change_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `kode_perubahan` varchar(255) NOT NULL,
  `tanggal` date NOT NULL,
  `perumahan_id` bigint(20) unsigned NOT NULL,
  `detail_rumah_id` bigint(20) unsigned DEFAULT NULL,
  `tahapan_pembangunan_id` bigint(20) unsigned DEFAULT NULL,
  `spk_kontraktor_id` bigint(20) unsigned DEFAULT NULL,
  `jenis_perubahan` varchar(255) NOT NULL DEFAULT 'pekerjaan_tambah',
  `uraian_perubahan` text NOT NULL,
  `alasan` text DEFAULT NULL,
  `estimasi_biaya` decimal(18,2) NOT NULL DEFAULT 0.00,
  `estimasi_hari` smallint(5) unsigned NOT NULL DEFAULT 0,
  `status` varchar(255) NOT NULL DEFAULT 'diajukan',
  `approval_status` varchar(255) NOT NULL DEFAULT 'menunggu_approval_manager',
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `record_status` varchar(255) NOT NULL DEFAULT 'draft',
  `locked_at` timestamp NULL DEFAULT NULL,
  `locked_by` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `work_change_requests_kode_perubahan_unique` (`kode_perubahan`),
  KEY `work_change_requests_perumahan_id_foreign` (`perumahan_id`),
  KEY `work_change_requests_detail_rumah_id_foreign` (`detail_rumah_id`),
  KEY `work_change_requests_tahapan_pembangunan_id_foreign` (`tahapan_pembangunan_id`),
  KEY `work_change_requests_spk_kontraktor_id_foreign` (`spk_kontraktor_id`),
  KEY `work_change_requests_approved_by_foreign` (`approved_by`),
  KEY `work_change_requests_locked_by_foreign` (`locked_by`),
  KEY `work_change_requests_created_by_foreign` (`created_by`),
  KEY `work_change_requests_updated_by_foreign` (`updated_by`),
  CONSTRAINT `work_change_requests_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `work_change_requests_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `work_change_requests_detail_rumah_id_foreign` FOREIGN KEY (`detail_rumah_id`) REFERENCES `detail_rumahs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `work_change_requests_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `work_change_requests_perumahan_id_foreign` FOREIGN KEY (`perumahan_id`) REFERENCES `perumahans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `work_change_requests_spk_kontraktor_id_foreign` FOREIGN KEY (`spk_kontraktor_id`) REFERENCES `spk_kontraktors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `work_change_requests_tahapan_pembangunan_id_foreign` FOREIGN KEY (`tahapan_pembangunan_id`) REFERENCES `tahapan_pembangunans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `work_change_requests_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `work_change_requests`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
