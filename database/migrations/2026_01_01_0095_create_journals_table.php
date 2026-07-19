<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `journals` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nomor_jurnal` varchar(255) NOT NULL,
  `tanggal` date NOT NULL,
  `type` varchar(255) NOT NULL,
  `source_type` varchar(255) DEFAULT NULL,
  `source_id` bigint(20) unsigned DEFAULT NULL,
  `perumahan_id` bigint(20) unsigned DEFAULT NULL,
  `detail_rumah_id` bigint(20) unsigned DEFAULT NULL,
  `total_debit` decimal(16,2) NOT NULL DEFAULT 0.00,
  `total_kredit` decimal(16,2) NOT NULL DEFAULT 0.00,
  `keterangan` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `journals_nomor_jurnal_unique` (`nomor_jurnal`),
  UNIQUE KEY `journals_source_type_source_id_type_unique` (`source_type`,`source_id`,`type`),
  KEY `journals_source_type_source_id_index` (`source_type`,`source_id`),
  KEY `journals_perumahan_id_foreign` (`perumahan_id`),
  KEY `journals_detail_rumah_id_foreign` (`detail_rumah_id`),
  KEY `journals_created_by_foreign` (`created_by`),
  KEY `journals_updated_by_foreign` (`updated_by`),
  CONSTRAINT `journals_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `journals_detail_rumah_id_foreign` FOREIGN KEY (`detail_rumah_id`) REFERENCES `detail_rumahs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `journals_perumahan_id_foreign` FOREIGN KEY (`perumahan_id`) REFERENCES `perumahans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `journals_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `journals`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
