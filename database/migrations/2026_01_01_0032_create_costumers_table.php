<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `costumers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `kode_costumer` varchar(255) NOT NULL,
  `marketing_lead_source_id` bigint(20) unsigned DEFAULT NULL,
  `marketing_campaign_id` bigint(20) unsigned DEFAULT NULL,
  `status_lead` varchar(255) NOT NULL DEFAULT 'lead_baru',
  `nama` varchar(255) NOT NULL,
  `jenis_kelamin` varchar(255) NOT NULL,
  `jenis_identitas` varchar(255) NOT NULL,
  `no_identitas` varchar(255) NOT NULL,
  `tanggal_lahir` date DEFAULT NULL,
  `tempat_lahir` varchar(255) DEFAULT NULL,
  `status_perkawinan` varchar(255) NOT NULL,
  `alamat` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `npwp` varchar(255) DEFAULT NULL,
  `telepon` varchar(255) DEFAULT NULL,
  `file_identitas` varchar(255) DEFAULT NULL,
  `penghasilan` double DEFAULT NULL,
  `pengeluaran_bulanan` decimal(18,2) DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `pekerjaan` varchar(255) DEFAULT NULL,
  `employment_category` varchar(255) DEFAULT NULL,
  `nama_perusahaan` varchar(255) DEFAULT NULL,
  `alamat_perusahaan` varchar(255) DEFAULT NULL,
  `telepon_perusahaan` varchar(255) DEFAULT NULL,
  `keterangan_perusahaan` text DEFAULT NULL,
  `nama_lengkap_pasangan` varchar(255) DEFAULT NULL,
  `jenis_kelamin_pasangan` varchar(255) DEFAULT NULL,
  `jenis_identitas_pasangan` varchar(255) DEFAULT NULL,
  `no_identitas_pasangan` varchar(255) DEFAULT NULL,
  `tanggal_lahir_pasangan` date DEFAULT NULL,
  `tempat_lahir_pasangan` varchar(255) DEFAULT NULL,
  `pekerjaan_pasangan` varchar(255) DEFAULT NULL,
  `penghasilan_pasangan` decimal(18,2) DEFAULT NULL,
  `pengeluaran_bulanan_pasangan` decimal(18,2) DEFAULT NULL,
  `daftar_cicilan` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`daftar_cicilan`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `record_status` varchar(255) NOT NULL DEFAULT 'draft',
  `locked_at` timestamp NULL DEFAULT NULL,
  `locked_by` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `perumahan_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `costumers_kode_costumer_unique` (`kode_costumer`),
  KEY `costumers_locked_by_foreign` (`locked_by`),
  KEY `costumers_created_by_foreign` (`created_by`),
  KEY `costumers_updated_by_foreign` (`updated_by`),
  KEY `costumers_marketing_lead_source_id_foreign` (`marketing_lead_source_id`),
  KEY `costumers_marketing_campaign_id_foreign` (`marketing_campaign_id`),
  KEY `costumers_perumahan_id_foreign` (`perumahan_id`),
  CONSTRAINT `costumers_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `costumers_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `costumers_marketing_campaign_id_foreign` FOREIGN KEY (`marketing_campaign_id`) REFERENCES `marketing_campaigns` (`id`) ON DELETE SET NULL,
  CONSTRAINT `costumers_marketing_lead_source_id_foreign` FOREIGN KEY (`marketing_lead_source_id`) REFERENCES `marketing_lead_sources` (`id`) ON DELETE SET NULL,
  CONSTRAINT `costumers_perumahan_id_foreign` FOREIGN KEY (`perumahan_id`) REFERENCES `perumahans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `costumers_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `costumers`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
