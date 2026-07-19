<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `hpp_realisasis` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `target_type` varchar(255) NOT NULL,
  `target_id` bigint(20) unsigned NOT NULL,
  `perumahan_id` bigint(20) unsigned DEFAULT NULL,
  `detail_rumah_id` bigint(20) unsigned DEFAULT NULL,
  `tahapan_pembangunan_id` bigint(20) unsigned DEFAULT NULL,
  `kelompok_hpp_id` bigint(20) unsigned DEFAULT NULL,
  `detail_rumah_hpp_item_id` bigint(20) unsigned DEFAULT NULL,
  `source_type` varchar(255) DEFAULT NULL,
  `source_id` bigint(20) unsigned DEFAULT NULL,
  `sumber_type` varchar(255) DEFAULT NULL,
  `sumber_id` bigint(20) unsigned DEFAULT NULL,
  `tanggal` date NOT NULL,
  `nominal` decimal(16,2) NOT NULL,
  `keterangan` text DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hpp_realisasis_perumahan_id_foreign` (`perumahan_id`),
  KEY `hpp_realisasis_detail_rumah_id_foreign` (`detail_rumah_id`),
  KEY `hpp_realisasis_tahapan_pembangunan_id_foreign` (`tahapan_pembangunan_id`),
  KEY `hpp_realisasis_kelompok_hpp_id_foreign` (`kelompok_hpp_id`),
  KEY `hpp_realisasis_user_id_foreign` (`user_id`),
  KEY `hpp_realisasis_target_type_target_id_index` (`target_type`,`target_id`),
  KEY `hpp_realisasis_sumber_type_sumber_id_index` (`sumber_type`,`sumber_id`),
  KEY `hpp_realisasis_created_by_foreign` (`created_by`),
  KEY `hpp_realisasis_updated_by_foreign` (`updated_by`),
  KEY `hpp_realisasis_source_index` (`source_type`,`source_id`),
  KEY `hpp_realisasis_detail_rumah_hpp_item_id_foreign` (`detail_rumah_hpp_item_id`),
  CONSTRAINT `hpp_realisasis_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `hpp_realisasis_detail_rumah_hpp_item_id_foreign` FOREIGN KEY (`detail_rumah_hpp_item_id`) REFERENCES `detail_rumah_hpp_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `hpp_realisasis_detail_rumah_id_foreign` FOREIGN KEY (`detail_rumah_id`) REFERENCES `detail_rumahs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `hpp_realisasis_kelompok_hpp_id_foreign` FOREIGN KEY (`kelompok_hpp_id`) REFERENCES `kelompok_hpps` (`id`) ON DELETE SET NULL,
  CONSTRAINT `hpp_realisasis_perumahan_id_foreign` FOREIGN KEY (`perumahan_id`) REFERENCES `perumahans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `hpp_realisasis_tahapan_pembangunan_id_foreign` FOREIGN KEY (`tahapan_pembangunan_id`) REFERENCES `tahapan_pembangunans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `hpp_realisasis_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `hpp_realisasis_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `hpp_realisasis`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
