<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `realisasi_hpps` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `detail_perumahan_hpp_id` bigint(20) unsigned NOT NULL,
  `tanggal` date NOT NULL,
  `nominal` double NOT NULL,
  `keterangan` text NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `realisasi_hpps_detail_perumahan_hpp_id_foreign` (`detail_perumahan_hpp_id`),
  KEY `realisasi_hpps_user_id_foreign` (`user_id`),
  KEY `realisasi_hpps_created_by_foreign` (`created_by`),
  KEY `realisasi_hpps_updated_by_foreign` (`updated_by`),
  CONSTRAINT `realisasi_hpps_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `realisasi_hpps_detail_perumahan_hpp_id_foreign` FOREIGN KEY (`detail_perumahan_hpp_id`) REFERENCES `detail_perumahan_hpps` (`id`) ON DELETE CASCADE,
  CONSTRAINT `realisasi_hpps_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `realisasi_hpps_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `realisasi_hpps`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
