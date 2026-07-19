<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `material_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `gudang_id` bigint(20) unsigned DEFAULT NULL,
  `kode_request` varchar(255) NOT NULL,
  `tanggal` date NOT NULL,
  `perumahan_id` bigint(20) unsigned DEFAULT NULL,
  `detail_rumah_id` bigint(20) unsigned DEFAULT NULL,
  `tahapan_pembangunan_id` bigint(20) unsigned DEFAULT NULL,
  `site_schedule_id` bigint(20) unsigned DEFAULT NULL,
  `progress_diakui` decimal(5,2) NOT NULL DEFAULT 0.00,
  `progress_pembangunan_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'diajukan',
  `keterangan` text DEFAULT NULL,
  `requested_by` bigint(20) unsigned DEFAULT NULL,
  `processed_by` bigint(20) unsigned DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `approved_by_gudang` bigint(20) unsigned DEFAULT NULL,
  `approved_at_gudang` timestamp NULL DEFAULT NULL,
  `approved_by_owner` bigint(20) unsigned DEFAULT NULL,
  `approved_at_owner` timestamp NULL DEFAULT NULL,
  `owner_approval_note` text DEFAULT NULL,
  `issued_by` bigint(20) unsigned DEFAULT NULL,
  `issued_at` timestamp NULL DEFAULT NULL,
  `transaksi_logistik_id` bigint(20) unsigned DEFAULT NULL,
  `approval_note` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `record_status` varchar(255) NOT NULL DEFAULT 'draft',
  `locked_at` timestamp NULL DEFAULT NULL,
  `locked_by` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `material_requests_kode_request_unique` (`kode_request`),
  KEY `material_requests_perumahan_id_foreign` (`perumahan_id`),
  KEY `material_requests_detail_rumah_id_foreign` (`detail_rumah_id`),
  KEY `material_requests_tahapan_pembangunan_id_foreign` (`tahapan_pembangunan_id`),
  KEY `material_requests_requested_by_foreign` (`requested_by`),
  KEY `material_requests_processed_by_foreign` (`processed_by`),
  KEY `material_requests_gudang_id_foreign` (`gudang_id`),
  KEY `material_requests_approved_by_gudang_foreign` (`approved_by_gudang`),
  KEY `material_requests_locked_by_foreign` (`locked_by`),
  KEY `material_requests_created_by_foreign` (`created_by`),
  KEY `material_requests_updated_by_foreign` (`updated_by`),
  KEY `material_requests_approved_by_owner_foreign` (`approved_by_owner`),
  KEY `material_requests_issued_by_foreign` (`issued_by`),
  KEY `material_requests_transaksi_logistik_id_foreign` (`transaksi_logistik_id`),
  KEY `material_requests_site_schedule_id_foreign` (`site_schedule_id`),
  KEY `material_requests_progress_pembangunan_id_foreign` (`progress_pembangunan_id`),
  CONSTRAINT `material_requests_approved_by_gudang_foreign` FOREIGN KEY (`approved_by_gudang`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_requests_approved_by_owner_foreign` FOREIGN KEY (`approved_by_owner`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_requests_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_requests_detail_rumah_id_foreign` FOREIGN KEY (`detail_rumah_id`) REFERENCES `detail_rumahs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_requests_gudang_id_foreign` FOREIGN KEY (`gudang_id`) REFERENCES `gudangs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_requests_issued_by_foreign` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_requests_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_requests_perumahan_id_foreign` FOREIGN KEY (`perumahan_id`) REFERENCES `perumahans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_requests_processed_by_foreign` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_requests_progress_pembangunan_id_foreign` FOREIGN KEY (`progress_pembangunan_id`) REFERENCES `progress_pembangunans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_requests_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_requests_site_schedule_id_foreign` FOREIGN KEY (`site_schedule_id`) REFERENCES `site_schedules` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_requests_tahapan_pembangunan_id_foreign` FOREIGN KEY (`tahapan_pembangunan_id`) REFERENCES `tahapan_pembangunans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_requests_transaksi_logistik_id_foreign` FOREIGN KEY (`transaksi_logistik_id`) REFERENCES `transaksi_logistiks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_requests_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `material_requests`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
