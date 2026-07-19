<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `site_schedules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `kode_jadwal` varchar(255) NOT NULL,
  `batch_code` varchar(255) DEFAULT NULL,
  `perumahan_id` bigint(20) unsigned NOT NULL,
  `detail_rumah_id` bigint(20) unsigned DEFAULT NULL,
  `spk_kontraktor_id` bigint(20) unsigned DEFAULT NULL,
  `spk_plan_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`spk_plan_json`)),
  `tahapan_pembangunan_id` bigint(20) unsigned DEFAULT NULL,
  `nama_pekerjaan` varchar(255) NOT NULL,
  `tanggal_mulai` date NOT NULL,
  `tanggal_target` date NOT NULL,
  `target_progress` decimal(5,2) NOT NULL DEFAULT 100.00,
  `realisasi_progress` decimal(5,2) NOT NULL DEFAULT 0.00,
  `status` varchar(255) NOT NULL DEFAULT 'direncanakan',
  `kendala` text DEFAULT NULL,
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
  UNIQUE KEY `site_schedules_kode_jadwal_unique` (`kode_jadwal`),
  KEY `site_schedules_perumahan_id_foreign` (`perumahan_id`),
  KEY `site_schedules_detail_rumah_id_foreign` (`detail_rumah_id`),
  KEY `site_schedules_tahapan_pembangunan_id_foreign` (`tahapan_pembangunan_id`),
  KEY `site_schedules_locked_by_foreign` (`locked_by`),
  KEY `site_schedules_created_by_foreign` (`created_by`),
  KEY `site_schedules_updated_by_foreign` (`updated_by`),
  KEY `site_schedules_batch_code_index` (`batch_code`),
  KEY `site_schedules_spk_kontraktor_id_foreign` (`spk_kontraktor_id`),
  CONSTRAINT `site_schedules_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `site_schedules_detail_rumah_id_foreign` FOREIGN KEY (`detail_rumah_id`) REFERENCES `detail_rumahs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `site_schedules_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `site_schedules_perumahan_id_foreign` FOREIGN KEY (`perumahan_id`) REFERENCES `perumahans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `site_schedules_spk_kontraktor_id_foreign` FOREIGN KEY (`spk_kontraktor_id`) REFERENCES `spk_kontraktors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `site_schedules_tahapan_pembangunan_id_foreign` FOREIGN KEY (`tahapan_pembangunan_id`) REFERENCES `tahapan_pembangunans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `site_schedules_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `site_schedules`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
