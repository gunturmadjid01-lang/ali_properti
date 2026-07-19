<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `marketing_commissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `kode_komisi` varchar(255) NOT NULL,
  `spr_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `dasar_perhitungan` decimal(18,2) NOT NULL DEFAULT 0.00,
  `persentase` decimal(8,4) NOT NULL DEFAULT 0.0000,
  `nominal` decimal(18,2) NOT NULL DEFAULT 0.00,
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `tanggal_jatuh_tempo` date DEFAULT NULL,
  `tanggal_dibayar` date DEFAULT NULL,
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
  UNIQUE KEY `marketing_commissions_kode_komisi_unique` (`kode_komisi`),
  KEY `marketing_commissions_spr_id_foreign` (`spr_id`),
  KEY `marketing_commissions_user_id_foreign` (`user_id`),
  KEY `marketing_commissions_locked_by_foreign` (`locked_by`),
  KEY `marketing_commissions_created_by_foreign` (`created_by`),
  KEY `marketing_commissions_updated_by_foreign` (`updated_by`),
  CONSTRAINT `marketing_commissions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `marketing_commissions_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `marketing_commissions_spr_id_foreign` FOREIGN KEY (`spr_id`) REFERENCES `sprs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `marketing_commissions_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `marketing_commissions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `marketing_commissions`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
