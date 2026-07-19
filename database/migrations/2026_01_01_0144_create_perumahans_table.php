<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `perumahans` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cabang_id` bigint(20) unsigned NOT NULL,
  `kode_proyek` varchar(255) DEFAULT NULL,
  `nama_perusahaan` varchar(255) NOT NULL,
  `developer_name` varchar(255) DEFAULT NULL,
  `alamat` varchar(255) NOT NULL,
  `latitude` varchar(255) DEFAULT NULL,
  `longtitude` varchar(255) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `luas_lahan` varchar(255) NOT NULL,
  `luas_komersial` varchar(255) DEFAULT NULL,
  `luas_fasos_fasum` varchar(255) DEFAULT NULL,
  `jumlah_unit` int(11) NOT NULL,
  `total_blok` int(11) NOT NULL DEFAULT 0,
  `harga_mulai` decimal(16,2) NOT NULL DEFAULT 0.00,
  `tanggal_mulai` date NOT NULL,
  `tanggal_target_selesai` date DEFAULT NULL,
  `jenis_sertifikat` varchar(255) DEFAULT NULL,
  `nomor_sertifikat_induk` varchar(255) DEFAULT NULL,
  `nama_marketing` varchar(255) DEFAULT NULL,
  `phone_marketing` varchar(255) DEFAULT NULL,
  `email_marketing` varchar(255) DEFAULT NULL,
  `deskripsi` text DEFAULT NULL,
  `status` varchar(255) NOT NULL,
  `record_status` varchar(255) NOT NULL DEFAULT 'draft',
  `locked_at` timestamp NULL DEFAULT NULL,
  `locked_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `perumahans_kode_proyek_unique` (`kode_proyek`),
  KEY `perumahans_cabang_id_foreign` (`cabang_id`),
  KEY `perumahans_locked_by_foreign` (`locked_by`),
  KEY `perumahans_created_by_foreign` (`created_by`),
  KEY `perumahans_updated_by_foreign` (`updated_by`),
  CONSTRAINT `perumahans_cabang_id_foreign` FOREIGN KEY (`cabang_id`) REFERENCES `cabang_perusahaans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `perumahans_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `perumahans_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `perumahans_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `perumahans`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
