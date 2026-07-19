<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `site_reports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `kode_laporan` varchar(255) NOT NULL,
  `jenis_laporan` varchar(255) NOT NULL DEFAULT 'harian',
  `tanggal` date NOT NULL,
  `periode_mulai` date DEFAULT NULL,
  `periode_selesai` date DEFAULT NULL,
  `perumahan_id` bigint(20) unsigned NOT NULL,
  `detail_rumah_id` bigint(20) unsigned DEFAULT NULL,
  `tahapan_pembangunan_id` bigint(20) unsigned DEFAULT NULL,
  `site_schedule_id` bigint(20) unsigned DEFAULT NULL,
  `progress_pembangunan_id` bigint(20) unsigned DEFAULT NULL,
  `cuaca` varchar(255) DEFAULT NULL,
  `jumlah_pekerja` int(10) unsigned NOT NULL DEFAULT 0,
  `kontraktor` varchar(255) DEFAULT NULL,
  `pekerjaan_selesai` text NOT NULL,
  `pekerjaan_tertahan` text DEFAULT NULL,
  `kendala` text DEFAULT NULL,
  `koordinasi` text DEFAULT NULL,
  `rencana_berikutnya` text DEFAULT NULL,
  `lampiran` varchar(255) DEFAULT NULL,
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
  UNIQUE KEY `site_reports_kode_laporan_unique` (`kode_laporan`),
  KEY `site_reports_perumahan_id_foreign` (`perumahan_id`),
  KEY `site_reports_detail_rumah_id_foreign` (`detail_rumah_id`),
  KEY `site_reports_tahapan_pembangunan_id_foreign` (`tahapan_pembangunan_id`),
  KEY `site_reports_approved_by_foreign` (`approved_by`),
  KEY `site_reports_locked_by_foreign` (`locked_by`),
  KEY `site_reports_created_by_foreign` (`created_by`),
  KEY `site_reports_updated_by_foreign` (`updated_by`),
  KEY `site_reports_site_schedule_id_foreign` (`site_schedule_id`),
  KEY `site_reports_progress_pembangunan_id_foreign` (`progress_pembangunan_id`),
  CONSTRAINT `site_reports_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `site_reports_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `site_reports_detail_rumah_id_foreign` FOREIGN KEY (`detail_rumah_id`) REFERENCES `detail_rumahs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `site_reports_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `site_reports_perumahan_id_foreign` FOREIGN KEY (`perumahan_id`) REFERENCES `perumahans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `site_reports_progress_pembangunan_id_foreign` FOREIGN KEY (`progress_pembangunan_id`) REFERENCES `progress_pembangunans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `site_reports_site_schedule_id_foreign` FOREIGN KEY (`site_schedule_id`) REFERENCES `site_schedules` (`id`) ON DELETE SET NULL,
  CONSTRAINT `site_reports_tahapan_pembangunan_id_foreign` FOREIGN KEY (`tahapan_pembangunan_id`) REFERENCES `tahapan_pembangunans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `site_reports_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `site_reports`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
