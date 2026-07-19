<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `internal_handovers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `kode_serah_terima` varchar(255) NOT NULL,
  `tanggal` date NOT NULL,
  `perumahan_id` bigint(20) unsigned NOT NULL,
  `detail_rumah_id` bigint(20) unsigned NOT NULL,
  `progress_unit` decimal(5,2) NOT NULL DEFAULT 0.00,
  `kondisi_bangunan` varchar(255) NOT NULL DEFAULT 'siap_review',
  `checklist` text DEFAULT NULL,
  `catatan` text DEFAULT NULL,
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
  UNIQUE KEY `internal_handovers_kode_serah_terima_unique` (`kode_serah_terima`),
  KEY `internal_handovers_perumahan_id_foreign` (`perumahan_id`),
  KEY `internal_handovers_detail_rumah_id_foreign` (`detail_rumah_id`),
  KEY `internal_handovers_approved_by_foreign` (`approved_by`),
  KEY `internal_handovers_locked_by_foreign` (`locked_by`),
  KEY `internal_handovers_created_by_foreign` (`created_by`),
  KEY `internal_handovers_updated_by_foreign` (`updated_by`),
  CONSTRAINT `internal_handovers_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `internal_handovers_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `internal_handovers_detail_rumah_id_foreign` FOREIGN KEY (`detail_rumah_id`) REFERENCES `detail_rumahs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `internal_handovers_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `internal_handovers_perumahan_id_foreign` FOREIGN KEY (`perumahan_id`) REFERENCES `perumahans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `internal_handovers_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `internal_handovers`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
