<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `attendance_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cabang_perusahaan_id` bigint(20) unsigned NOT NULL,
  `check_in_time` time NOT NULL DEFAULT '08:00:00',
  `check_out_time` time NOT NULL DEFAULT '17:00:00',
  `late_tolerance_minutes` smallint(5) unsigned NOT NULL DEFAULT 15,
  `checkout_tolerance_minutes` smallint(5) unsigned NOT NULL DEFAULT 15,
  `work_days` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`work_days`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `record_status` varchar(255) NOT NULL DEFAULT 'draft',
  `locked_at` timestamp NULL DEFAULT NULL,
  `locked_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `attendance_settings_cabang_perusahaan_id_unique` (`cabang_perusahaan_id`),
  KEY `attendance_settings_locked_by_foreign` (`locked_by`),
  CONSTRAINT `attendance_settings_cabang_perusahaan_id_foreign` FOREIGN KEY (`cabang_perusahaan_id`) REFERENCES `cabang_perusahaans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_settings_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `attendance_settings`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
