<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `kantor_cabang_id` bigint(20) unsigned DEFAULT NULL,
  `employee_number` varchar(50) DEFAULT NULL,
  `gudang_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `job_title` varchar(100) DEFAULT NULL,
  `job_position_id` bigint(20) unsigned DEFAULT NULL,
  `join_date` date DEFAULT NULL,
  `employment_type` varchar(30) NOT NULL DEFAULT 'tetap',
  `employment_status` varchar(30) NOT NULL DEFAULT 'aktif',
  `has_login_access` tinyint(1) NOT NULL DEFAULT 1,
  `attendance_pin` varchar(255) DEFAULT NULL,
  `phone` varchar(255) NOT NULL,
  `tax_number` varchar(50) DEFAULT NULL,
  `bpjs_health_number` varchar(50) DEFAULT NULL,
  `bpjs_employment_number` varchar(50) DEFAULT NULL,
  `payroll_bank_name` varchar(100) DEFAULT NULL,
  `payroll_bank_account` varchar(100) DEFAULT NULL,
  `payroll_bank_holder` varchar(100) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `record_status` varchar(255) NOT NULL DEFAULT 'draft',
  `locked_at` timestamp NULL DEFAULT NULL,
  `locked_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_employee_number_unique` (`employee_number`),
  KEY `users_locked_by_foreign` (`locked_by`),
  KEY `users_gudang_id_foreign` (`gudang_id`),
  KEY `users_job_position_id_foreign` (`job_position_id`),
  CONSTRAINT `users_gudang_id_foreign` FOREIGN KEY (`gudang_id`) REFERENCES `gudangs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_job_position_id_foreign` FOREIGN KEY (`job_position_id`) REFERENCES `job_positions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `users`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
