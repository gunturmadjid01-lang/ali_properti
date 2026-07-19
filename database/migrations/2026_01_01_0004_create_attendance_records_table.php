<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `attendance_records` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `cabang_perusahaan_id` bigint(20) unsigned NOT NULL,
  `attendance_date` date NOT NULL,
  `type` enum('check_in','check_out') NOT NULL,
  `time_status` varchar(255) NOT NULL DEFAULT 'on_time',
  `schedule_difference_minutes` int(11) DEFAULT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `accuracy_meters` int(10) unsigned DEFAULT NULL,
  `distance_meters` decimal(10,2) NOT NULL,
  `is_within_radius` tinyint(1) NOT NULL DEFAULT 1,
  `outside_radius_confirmed` tinyint(1) NOT NULL DEFAULT 0,
  `photo_path` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `record_status` varchar(255) NOT NULL DEFAULT 'draft',
  `locked_at` timestamp NULL DEFAULT NULL,
  `locked_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `attendance_records_user_id_attendance_date_type_unique` (`user_id`,`attendance_date`,`type`),
  KEY `attendance_records_locked_by_foreign` (`locked_by`),
  KEY `attendance_records_cabang_perusahaan_id_attendance_date_index` (`cabang_perusahaan_id`,`attendance_date`),
  CONSTRAINT `attendance_records_cabang_perusahaan_id_foreign` FOREIGN KEY (`cabang_perusahaan_id`) REFERENCES `cabang_perusahaans` (`id`),
  CONSTRAINT `attendance_records_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `attendance_records_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `attendance_records`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
