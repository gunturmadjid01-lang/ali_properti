<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `tukang_gajis` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tukang_id` bigint(20) unsigned NOT NULL,
  `nominal` decimal(16,2) NOT NULL,
  `tanggal_berlaku` date NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'aktif',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tukang_gajis_created_by_foreign` (`created_by`),
  KEY `tukang_gajis_updated_by_foreign` (`updated_by`),
  KEY `tukang_gajis_tukang_id_status_tanggal_berlaku_index` (`tukang_id`,`status`,`tanggal_berlaku`),
  CONSTRAINT `tukang_gajis_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tukang_gajis_tukang_id_foreign` FOREIGN KEY (`tukang_id`) REFERENCES `tukangs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tukang_gajis_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `tukang_gajis`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
