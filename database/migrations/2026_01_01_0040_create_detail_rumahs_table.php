<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `detail_rumahs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `perumahan_id` bigint(20) unsigned NOT NULL,
  `kode_nlok` varchar(255) NOT NULL,
  `nomor_rumah` varchar(255) NOT NULL,
  `tipe_rumah` varchar(255) DEFAULT NULL,
  `model_unit` varchar(255) DEFAULT NULL,
  `luas_bangunan` varchar(255) DEFAULT NULL,
  `luas_tanah` varchar(255) NOT NULL,
  `jumlah_lantai` tinyint(3) unsigned DEFAULT NULL,
  `kamar_tidur` tinyint(3) unsigned DEFAULT NULL,
  `kamar_mandi` tinyint(3) unsigned DEFAULT NULL,
  `daya_listrik` varchar(255) DEFAULT NULL,
  `sumber_air` varchar(255) DEFAULT NULL,
  `carport` varchar(255) DEFAULT NULL,
  `arah_hadap` varchar(255) DEFAULT NULL,
  `posisi_unit` varchar(255) DEFAULT NULL,
  `harga_jual` decimal(16,2) NOT NULL DEFAULT 0.00,
  `status_penjualan` varchar(255) NOT NULL DEFAULT 'tersedia',
  `booking_spr_id` bigint(20) unsigned DEFAULT NULL,
  `booking_at` timestamp NULL DEFAULT NULL,
  `status_pembangunan` varchar(255) NOT NULL DEFAULT 'kapling',
  `progress_terakhir` decimal(5,2) NOT NULL DEFAULT 0.00,
  `tanggal_mulai_bangun` date DEFAULT NULL,
  `tanggal_selesai_bangun` date DEFAULT NULL,
  `spesifikasi` text DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `status` varchar(255) NOT NULL,
  `record_status` varchar(255) NOT NULL DEFAULT 'draft',
  `locked_at` timestamp NULL DEFAULT NULL,
  `locked_by` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `detail_rumahs_perumahan_id_foreign` (`perumahan_id`),
  KEY `detail_rumahs_locked_by_foreign` (`locked_by`),
  KEY `detail_rumahs_created_by_foreign` (`created_by`),
  KEY `detail_rumahs_updated_by_foreign` (`updated_by`),
  KEY `detail_rumahs_booking_spr_id_foreign` (`booking_spr_id`),
  CONSTRAINT `detail_rumahs_booking_spr_id_foreign` FOREIGN KEY (`booking_spr_id`) REFERENCES `sprs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `detail_rumahs_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `detail_rumahs_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `detail_rumahs_perumahan_id_foreign` FOREIGN KEY (`perumahan_id`) REFERENCES `perumahans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `detail_rumahs_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `detail_rumahs`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
