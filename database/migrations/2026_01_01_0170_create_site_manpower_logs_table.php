<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `site_manpower_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `kode_log` varchar(255) NOT NULL,
  `tanggal` date NOT NULL,
  `perumahan_id` bigint(20) unsigned NOT NULL,
  `detail_rumah_id` bigint(20) unsigned DEFAULT NULL,
  `tahapan_pembangunan_id` bigint(20) unsigned DEFAULT NULL,
  `site_schedule_id` bigint(20) unsigned DEFAULT NULL,
  `progress_pembangunan_id` bigint(20) unsigned DEFAULT NULL,
  `spk_kontraktor_id` bigint(20) unsigned DEFAULT NULL,
  `sumber_tenaga_kerja` varchar(255) NOT NULL DEFAULT 'kontraktor',
  `kontraktor` varchar(255) DEFAULT NULL,
  `nama_mandor` varchar(255) DEFAULT NULL,
  `mandor` int(10) unsigned NOT NULL DEFAULT 0,
  `tukang` int(10) unsigned NOT NULL DEFAULT 0,
  `kenek` int(10) unsigned NOT NULL DEFAULT 0,
  `tipe_upah` varchar(255) DEFAULT NULL,
  `jumlah_periode` decimal(8,2) NOT NULL DEFAULT 1.00,
  `tarif_mandor` decimal(18,2) NOT NULL DEFAULT 0.00,
  `tarif_tukang` decimal(18,2) NOT NULL DEFAULT 0.00,
  `tarif_kenek` decimal(18,2) NOT NULL DEFAULT 0.00,
  `nilai_borongan` decimal(18,2) NOT NULL DEFAULT 0.00,
  `nilai_upah` decimal(18,2) NOT NULL DEFAULT 0.00,
  `jam_kerja` decimal(5,2) NOT NULL DEFAULT 8.00,
  `jam_lembur` decimal(5,2) NOT NULL DEFAULT 0.00,
  `tarif_lembur` decimal(18,2) NOT NULL DEFAULT 0.00,
  `sumber_alat` varchar(255) NOT NULL DEFAULT 'tidak_ada',
  `alat_digunakan` text DEFAULT NULL,
  `penyedia_alat` varchar(255) DEFAULT NULL,
  `biaya_sewa_alat` decimal(18,2) NOT NULL DEFAULT 0.00,
  `pekerjaan` text DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `record_status` varchar(255) NOT NULL DEFAULT 'draft',
  `locked_at` timestamp NULL DEFAULT NULL,
  `locked_by` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `site_manpower_logs_kode_log_unique` (`kode_log`),
  KEY `site_manpower_logs_perumahan_id_foreign` (`perumahan_id`),
  KEY `site_manpower_logs_detail_rumah_id_foreign` (`detail_rumah_id`),
  KEY `site_manpower_logs_spk_kontraktor_id_foreign` (`spk_kontraktor_id`),
  KEY `site_manpower_logs_locked_by_foreign` (`locked_by`),
  KEY `site_manpower_logs_created_by_foreign` (`created_by`),
  KEY `site_manpower_logs_updated_by_foreign` (`updated_by`),
  KEY `site_manpower_logs_tahapan_pembangunan_id_foreign` (`tahapan_pembangunan_id`),
  KEY `site_manpower_logs_site_schedule_id_foreign` (`site_schedule_id`),
  KEY `site_manpower_logs_progress_pembangunan_id_foreign` (`progress_pembangunan_id`),
  CONSTRAINT `site_manpower_logs_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `site_manpower_logs_detail_rumah_id_foreign` FOREIGN KEY (`detail_rumah_id`) REFERENCES `detail_rumahs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `site_manpower_logs_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `site_manpower_logs_perumahan_id_foreign` FOREIGN KEY (`perumahan_id`) REFERENCES `perumahans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `site_manpower_logs_progress_pembangunan_id_foreign` FOREIGN KEY (`progress_pembangunan_id`) REFERENCES `progress_pembangunans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `site_manpower_logs_site_schedule_id_foreign` FOREIGN KEY (`site_schedule_id`) REFERENCES `site_schedules` (`id`) ON DELETE SET NULL,
  CONSTRAINT `site_manpower_logs_spk_kontraktor_id_foreign` FOREIGN KEY (`spk_kontraktor_id`) REFERENCES `spk_kontraktors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `site_manpower_logs_tahapan_pembangunan_id_foreign` FOREIGN KEY (`tahapan_pembangunan_id`) REFERENCES `tahapan_pembangunans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `site_manpower_logs_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `site_manpower_logs`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
