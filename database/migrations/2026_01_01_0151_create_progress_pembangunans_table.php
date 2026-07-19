<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `progress_pembangunans` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `detail_rumah_id` bigint(20) unsigned DEFAULT NULL,
  `tahapan_pembangunan_id` bigint(20) unsigned DEFAULT NULL,
  `site_schedule_id` bigint(20) unsigned DEFAULT NULL,
  `schedule_stage_key` varchar(255) DEFAULT NULL,
  `schedule_stage_name` varchar(255) DEFAULT NULL,
  `schedule_stage_target` decimal(8,2) NOT NULL DEFAULT 0.00,
  `schedule_item_key` varchar(255) DEFAULT NULL,
  `schedule_item_name` varchar(255) DEFAULT NULL,
  `schedule_item_target` decimal(8,2) NOT NULL DEFAULT 0.00,
  `nama_progress` varchar(255) DEFAULT NULL,
  `tanggal` date NOT NULL,
  `tahapan` double NOT NULL,
  `persentase` double NOT NULL,
  `persentase_total` decimal(5,2) NOT NULL DEFAULT 0.00,
  `keterangan` text NOT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `approval_status` varchar(255) NOT NULL DEFAULT 'menunggu_approval_manager',
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `approved_note` text DEFAULT NULL,
  `source_type` varchar(255) DEFAULT NULL,
  `source_id` bigint(20) unsigned DEFAULT NULL,
  `source_label` varchar(255) DEFAULT NULL,
  `record_status` varchar(255) NOT NULL DEFAULT 'draft',
  `locked_at` timestamp NULL DEFAULT NULL,
  `locked_by` bigint(20) unsigned DEFAULT NULL,
  `users_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `progress_pembangunans_detail_rumah_id_foreign` (`detail_rumah_id`),
  KEY `progress_pembangunans_tahapan_pembangunan_id_foreign` (`tahapan_pembangunan_id`),
  KEY `progress_pembangunans_users_id_foreign` (`users_id`),
  KEY `progress_pembangunans_approved_by_foreign` (`approved_by`),
  KEY `progress_pembangunans_locked_by_foreign` (`locked_by`),
  KEY `progress_pembangunans_created_by_foreign` (`created_by`),
  KEY `progress_pembangunans_updated_by_foreign` (`updated_by`),
  KEY `progress_pembangunans_site_schedule_id_foreign` (`site_schedule_id`),
  KEY `progress_pembangunans_source_type_source_id_index` (`source_type`,`source_id`),
  CONSTRAINT `progress_pembangunans_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `progress_pembangunans_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `progress_pembangunans_detail_rumah_id_foreign` FOREIGN KEY (`detail_rumah_id`) REFERENCES `detail_rumahs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `progress_pembangunans_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `progress_pembangunans_site_schedule_id_foreign` FOREIGN KEY (`site_schedule_id`) REFERENCES `site_schedules` (`id`) ON DELETE SET NULL,
  CONSTRAINT `progress_pembangunans_tahapan_pembangunan_id_foreign` FOREIGN KEY (`tahapan_pembangunan_id`) REFERENCES `tahapan_pembangunans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `progress_pembangunans_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `progress_pembangunans_users_id_foreign` FOREIGN KEY (`users_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `progress_pembangunans`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
