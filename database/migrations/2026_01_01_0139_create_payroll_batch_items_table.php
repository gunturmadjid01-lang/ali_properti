<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `payroll_batch_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `payroll_batch_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `employee_number` varchar(255) DEFAULT NULL,
  `employee_name` varchar(255) NOT NULL,
  `job_position` varchar(255) NOT NULL,
  `basic_salary` decimal(18,2) NOT NULL,
  `fixed_allowance` decimal(18,2) NOT NULL DEFAULT 0.00,
  `other_allowance` decimal(18,2) NOT NULL DEFAULT 0.00,
  `deductions` decimal(18,2) NOT NULL DEFAULT 0.00,
  `advance_deduction` decimal(18,2) NOT NULL DEFAULT 0.00,
  `net_salary` decimal(18,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payroll_batch_items_payroll_batch_id_user_id_unique` (`payroll_batch_id`,`user_id`),
  KEY `payroll_batch_items_user_id_foreign` (`user_id`),
  CONSTRAINT `payroll_batch_items_payroll_batch_id_foreign` FOREIGN KEY (`payroll_batch_id`) REFERENCES `payroll_batches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payroll_batch_items_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `payroll_batch_items`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
