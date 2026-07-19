<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `marketing_campaigns` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `perumahan_id` bigint(20) unsigned DEFAULT NULL,
  `kode_campaign` varchar(255) NOT NULL,
  `nama_campaign` varchar(255) NOT NULL,
  `kanal` varchar(255) NOT NULL,
  `tanggal_mulai` date NOT NULL,
  `tanggal_selesai` date DEFAULT NULL,
  `anggaran` decimal(18,2) NOT NULL DEFAULT 0.00,
  `realisasi_biaya` decimal(18,2) NOT NULL DEFAULT 0.00,
  `target_lead` int(10) unsigned NOT NULL DEFAULT 0,
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `keterangan` text DEFAULT NULL,
  `record_status` varchar(255) NOT NULL DEFAULT 'draft',
  `locked_at` timestamp NULL DEFAULT NULL,
  `locked_by` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `marketing_campaigns_kode_campaign_unique` (`kode_campaign`),
  KEY `marketing_campaigns_locked_by_foreign` (`locked_by`),
  KEY `marketing_campaigns_created_by_foreign` (`created_by`),
  KEY `marketing_campaigns_updated_by_foreign` (`updated_by`),
  KEY `marketing_campaigns_perumahan_id_foreign` (`perumahan_id`),
  CONSTRAINT `marketing_campaigns_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `marketing_campaigns_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `marketing_campaigns_perumahan_id_foreign` FOREIGN KEY (`perumahan_id`) REFERENCES `perumahans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `marketing_campaigns_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `marketing_campaigns`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
