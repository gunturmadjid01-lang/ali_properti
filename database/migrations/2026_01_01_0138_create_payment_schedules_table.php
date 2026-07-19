<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `payment_schedules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_no` varchar(255) DEFAULT NULL,
  `sales_transaction_id` bigint(20) unsigned NOT NULL,
  `source_type` varchar(255) DEFAULT NULL,
  `source_id` bigint(20) unsigned DEFAULT NULL,
  `sequence` smallint(5) unsigned NOT NULL,
  `type` varchar(255) NOT NULL,
  `description` varchar(255) NOT NULL,
  `issued_at` date DEFAULT NULL,
  `due_date` date NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `paid_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `status` varchar(255) NOT NULL DEFAULT 'belum_dibayar',
  `record_status` varchar(255) NOT NULL DEFAULT 'draft',
  `calculation_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`calculation_snapshot`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `locked_at` timestamp NULL DEFAULT NULL,
  `locked_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_schedule_source_sequence_unique` (`sales_transaction_id`,`source_type`,`source_id`,`sequence`),
  UNIQUE KEY `payment_schedules_invoice_no_unique` (`invoice_no`),
  KEY `payment_schedules_source_type_source_id_index` (`source_type`,`source_id`),
  KEY `payment_schedules_locked_by_foreign` (`locked_by`),
  CONSTRAINT `payment_schedules_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payment_schedules_sales_transaction_id_foreign` FOREIGN KEY (`sales_transaction_id`) REFERENCES `sales_transactions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `payment_schedules`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
