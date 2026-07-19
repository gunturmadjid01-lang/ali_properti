<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `tahapan_pembangunans` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nama_tahapan` varchar(255) NOT NULL,
  `bobot_persen` decimal(5,2) NOT NULL DEFAULT 0.00,
  `urutan` int(10) unsigned NOT NULL DEFAULT 0,
  `status` varchar(255) NOT NULL DEFAULT 'aktif',
  `konteks` varchar(255) NOT NULL DEFAULT 'unit',
  `perumahan_id` bigint(20) unsigned DEFAULT NULL,
  `detail_rumah_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tahapan_pembangunans_created_by_foreign` (`created_by`),
  KEY `tahapan_pembangunans_updated_by_foreign` (`updated_by`),
  KEY `tahapan_pembangunans_perumahan_id_foreign` (`perumahan_id`),
  KEY `tahapan_pembangunans_detail_rumah_id_foreign` (`detail_rumah_id`),
  CONSTRAINT `tahapan_pembangunans_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tahapan_pembangunans_detail_rumah_id_foreign` FOREIGN KEY (`detail_rumah_id`) REFERENCES `detail_rumahs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tahapan_pembangunans_perumahan_id_foreign` FOREIGN KEY (`perumahan_id`) REFERENCES `perumahans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tahapan_pembangunans_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `tahapan_pembangunans`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
