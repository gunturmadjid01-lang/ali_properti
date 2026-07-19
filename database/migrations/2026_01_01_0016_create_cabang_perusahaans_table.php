<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `cabang_perusahaans` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `kode_cabang` varchar(255) NOT NULL,
  `nama_cabang` varchar(255) NOT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `address` text NOT NULL,
  `phone` varchar(255) NOT NULL,
  `latitude` varchar(255) DEFAULT NULL,
  `longtitude` varchar(255) DEFAULT NULL,
  `attendance_radius_meters` int(10) unsigned NOT NULL DEFAULT 100,
  `deskripsi` longtext DEFAULT NULL,
  `emaiil` varchar(255) NOT NULL,
  `manager_name` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL DEFAULT 'cabang',
  `record_status` varchar(255) NOT NULL DEFAULT 'draft',
  `locked_at` timestamp NULL DEFAULT NULL,
  `locked_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cabang_perusahaans_kode_cabang_unique` (`kode_cabang`),
  UNIQUE KEY `cabang_perusahaans_nama_cabang_unique` (`nama_cabang`),
  KEY `cabang_perusahaans_locked_by_foreign` (`locked_by`),
  KEY `cabang_perusahaans_created_by_foreign` (`created_by`),
  KEY `cabang_perusahaans_updated_by_foreign` (`updated_by`),
  CONSTRAINT `cabang_perusahaans_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cabang_perusahaans_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cabang_perusahaans_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `cabang_perusahaans`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
