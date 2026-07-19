<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `spk_kontraktors` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `kontraktor_id` bigint(20) unsigned DEFAULT NULL,
  `perumahan_id` bigint(20) unsigned DEFAULT NULL,
  `detail_rumah_id` bigint(20) unsigned DEFAULT NULL,
  `nomor_spk` varchar(255) NOT NULL,
  `judul_pekerjaan` varchar(255) NOT NULL,
  `jenis_pekerjaan` varchar(255) NOT NULL DEFAULT 'rumah',
  `sumber_tenaga_kerja` varchar(255) NOT NULL DEFAULT 'tukang_owner',
  `tanggal_spk` date NOT NULL,
  `tanggal_mulai` date DEFAULT NULL,
  `tanggal_selesai` date DEFAULT NULL,
  `nilai_kontrak_dasar` decimal(16,2) NOT NULL DEFAULT 0.00,
  `nilai_kontrak` decimal(16,2) NOT NULL DEFAULT 0.00,
  `metode_pembayaran` varchar(255) NOT NULL DEFAULT 'cash',
  `approval_role` varchar(255) NOT NULL DEFAULT 'manager',
  `lingkup_pekerjaan` text DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `record_status` varchar(255) NOT NULL DEFAULT 'draft',
  `locked_at` timestamp NULL DEFAULT NULL,
  `locked_by` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `spk_kontraktors_nomor_spk_unique` (`nomor_spk`),
  KEY `spk_kontraktors_kontraktor_id_foreign` (`kontraktor_id`),
  KEY `spk_kontraktors_perumahan_id_foreign` (`perumahan_id`),
  KEY `spk_kontraktors_detail_rumah_id_foreign` (`detail_rumah_id`),
  KEY `spk_kontraktors_created_by_foreign` (`created_by`),
  KEY `spk_kontraktors_updated_by_foreign` (`updated_by`),
  KEY `spk_kontraktors_locked_by_foreign` (`locked_by`),
  KEY `spk_kontraktors_approved_by_foreign` (`approved_by`),
  CONSTRAINT `spk_kontraktors_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `spk_kontraktors_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `spk_kontraktors_detail_rumah_id_foreign` FOREIGN KEY (`detail_rumah_id`) REFERENCES `detail_rumahs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `spk_kontraktors_kontraktor_id_foreign` FOREIGN KEY (`kontraktor_id`) REFERENCES `kontraktors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `spk_kontraktors_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `spk_kontraktors_perumahan_id_foreign` FOREIGN KEY (`perumahan_id`) REFERENCES `perumahans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `spk_kontraktors_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `spk_kontraktors`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
