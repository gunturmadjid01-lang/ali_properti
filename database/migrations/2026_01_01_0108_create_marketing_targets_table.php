<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `marketing_targets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `perumahan_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `tahun` smallint(5) unsigned NOT NULL,
  `bulan` tinyint(3) unsigned NOT NULL,
  `target_lead` int(10) unsigned NOT NULL DEFAULT 0,
  `target_survey` int(10) unsigned NOT NULL DEFAULT 0,
  `target_spr` int(10) unsigned NOT NULL DEFAULT 0,
  `target_closing` int(10) unsigned NOT NULL DEFAULT 0,
  `target_nilai_penjualan` decimal(18,2) NOT NULL DEFAULT 0.00,
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
  UNIQUE KEY `marketing_targets_user_id_tahun_bulan_unique` (`user_id`,`tahun`,`bulan`),
  UNIQUE KEY `marketing_targets_property_user_period_unique` (`perumahan_id`,`user_id`,`tahun`,`bulan`),
  KEY `marketing_targets_locked_by_foreign` (`locked_by`),
  KEY `marketing_targets_created_by_foreign` (`created_by`),
  KEY `marketing_targets_updated_by_foreign` (`updated_by`),
  CONSTRAINT `marketing_targets_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `marketing_targets_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `marketing_targets_perumahan_id_foreign` FOREIGN KEY (`perumahan_id`) REFERENCES `perumahans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `marketing_targets_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `marketing_targets_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `marketing_targets`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
