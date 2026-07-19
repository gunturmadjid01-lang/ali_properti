<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `employee_advances` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `advance_number` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `advance_date` date NOT NULL,
  `deduction_period` varchar(7) NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `purpose` text NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'draft',
  `record_status` varchar(20) NOT NULL DEFAULT 'draft',
  `locked_at` timestamp NULL DEFAULT NULL,
  `locked_by` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_advances_advance_number_unique` (`advance_number`),
  KEY `employee_advances_user_id_foreign` (`user_id`),
  KEY `employee_advances_locked_by_foreign` (`locked_by`),
  KEY `employee_advances_created_by_foreign` (`created_by`),
  KEY `employee_advances_updated_by_foreign` (`updated_by`),
  KEY `employee_advances_deduction_period_index` (`deduction_period`),
  KEY `employee_advances_status_index` (`status`),
  CONSTRAINT `employee_advances_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employee_advances_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employee_advances_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employee_advances_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `employee_advances`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
