<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `bank_kredits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `kode_bank` varchar(255) NOT NULL,
  `nama_bank` varchar(255) NOT NULL,
  `nama_pic` varchar(255) DEFAULT NULL,
  `telepon_pic` varchar(255) DEFAULT NULL,
  `email_pic` varchar(255) DEFAULT NULL,
  `bunga_tahunan` decimal(5,2) NOT NULL DEFAULT 7.50,
  `tenor_min_bulan` smallint(5) unsigned NOT NULL DEFAULT 60,
  `tenor_max_bulan` smallint(5) unsigned NOT NULL DEFAULT 240,
  `minimal_dp_persen` decimal(5,2) NOT NULL DEFAULT 10.00,
  `biaya_provisi_persen` decimal(5,2) NOT NULL DEFAULT 1.00,
  `biaya_admin` decimal(18,2) NOT NULL DEFAULT 0.00,
  `status` varchar(255) NOT NULL DEFAULT 'aktif',
  `record_status` varchar(255) NOT NULL DEFAULT 'draft',
  `locked_at` timestamp NULL DEFAULT NULL,
  `locked_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `jenis_bank` varchar(255) NOT NULL DEFAULT 'konvensional',
  `alamat_pusat` text DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `nomor_telepon` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bank_kredits_kode_bank_unique` (`kode_bank`),
  KEY `bank_kredits_locked_by_foreign` (`locked_by`),
  KEY `bank_kredits_created_by_foreign` (`created_by`),
  KEY `bank_kredits_updated_by_foreign` (`updated_by`),
  CONSTRAINT `bank_kredits_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bank_kredits_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bank_kredits_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `bank_kredits`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
