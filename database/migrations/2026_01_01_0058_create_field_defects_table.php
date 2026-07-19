<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `field_defects` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `kode_defect` varchar(255) NOT NULL,
  `tanggal` date NOT NULL,
  `perumahan_id` bigint(20) unsigned NOT NULL,
  `detail_rumah_id` bigint(20) unsigned DEFAULT NULL,
  `tahapan_pembangunan_id` bigint(20) unsigned DEFAULT NULL,
  `progress_pembangunan_id` bigint(20) unsigned DEFAULT NULL,
  `quality_inspection_id` bigint(20) unsigned DEFAULT NULL,
  `kategori` varchar(255) NOT NULL DEFAULT 'pekerjaan',
  `prioritas` varchar(255) NOT NULL DEFAULT 'medium',
  `temuan` text NOT NULL,
  `instruksi_perbaikan` text DEFAULT NULL,
  `target_selesai` date DEFAULT NULL,
  `tanggal_selesai` date DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'open',
  `foto` varchar(255) DEFAULT NULL,
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
  UNIQUE KEY `field_defects_kode_defect_unique` (`kode_defect`),
  KEY `field_defects_perumahan_id_foreign` (`perumahan_id`),
  KEY `field_defects_detail_rumah_id_foreign` (`detail_rumah_id`),
  KEY `field_defects_tahapan_pembangunan_id_foreign` (`tahapan_pembangunan_id`),
  KEY `field_defects_quality_inspection_id_foreign` (`quality_inspection_id`),
  KEY `field_defects_approved_by_foreign` (`approved_by`),
  KEY `field_defects_locked_by_foreign` (`locked_by`),
  KEY `field_defects_created_by_foreign` (`created_by`),
  KEY `field_defects_updated_by_foreign` (`updated_by`),
  KEY `field_defects_progress_pembangunan_id_foreign` (`progress_pembangunan_id`),
  CONSTRAINT `field_defects_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `field_defects_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `field_defects_detail_rumah_id_foreign` FOREIGN KEY (`detail_rumah_id`) REFERENCES `detail_rumahs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `field_defects_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `field_defects_perumahan_id_foreign` FOREIGN KEY (`perumahan_id`) REFERENCES `perumahans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `field_defects_progress_pembangunan_id_foreign` FOREIGN KEY (`progress_pembangunan_id`) REFERENCES `progress_pembangunans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `field_defects_quality_inspection_id_foreign` FOREIGN KEY (`quality_inspection_id`) REFERENCES `quality_inspections` (`id`) ON DELETE SET NULL,
  CONSTRAINT `field_defects_tahapan_pembangunan_id_foreign` FOREIGN KEY (`tahapan_pembangunan_id`) REFERENCES `tahapan_pembangunans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `field_defects_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `field_defects`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
