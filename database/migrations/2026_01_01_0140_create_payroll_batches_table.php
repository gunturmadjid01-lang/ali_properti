<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `payroll_batches` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `batch_number` varchar(50) NOT NULL,
  `period` varchar(7) NOT NULL,
  `payment_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `total_gross` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_deductions` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_net` decimal(18,2) NOT NULL DEFAULT 0.00,
  `status` varchar(30) NOT NULL DEFAULT 'draft',
  `record_status` varchar(20) NOT NULL DEFAULT 'draft',
  `locked_at` timestamp NULL DEFAULT NULL,
  `locked_by` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payroll_batches_batch_number_unique` (`batch_number`),
  KEY `payroll_batches_locked_by_foreign` (`locked_by`),
  KEY `payroll_batches_created_by_foreign` (`created_by`),
  KEY `payroll_batches_updated_by_foreign` (`updated_by`),
  KEY `payroll_batches_period_index` (`period`),
  KEY `payroll_batches_status_index` (`status`),
  KEY `payroll_batches_record_status_index` (`record_status`),
  CONSTRAINT `payroll_batches_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payroll_batches_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payroll_batches_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `payroll_batches`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
