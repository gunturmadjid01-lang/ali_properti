<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `sales_process_steps` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sales_transaction_id` bigint(20) unsigned NOT NULL,
  `sequence` smallint(5) unsigned NOT NULL,
  `code` varchar(255) NOT NULL,
  `label` varchar(255) NOT NULL,
  `category` varchar(255) NOT NULL,
  `assigned_to` bigint(20) unsigned DEFAULT NULL,
  `description` text DEFAULT NULL,
  `planned_date` date DEFAULT NULL,
  `actual_date` date DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'waiting',
  `outcome` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `attachment_path` varchar(255) DEFAULT NULL,
  `attachment_original_name` varchar(255) DEFAULT NULL,
  `record_status` varchar(255) NOT NULL DEFAULT 'draft',
  `locked_at` timestamp NULL DEFAULT NULL,
  `locked_by` bigint(20) unsigned DEFAULT NULL,
  `completed_by` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sales_process_steps_sales_transaction_id_code_unique` (`sales_transaction_id`,`code`),
  KEY `sales_process_steps_locked_by_foreign` (`locked_by`),
  KEY `sales_process_steps_completed_by_foreign` (`completed_by`),
  KEY `sales_process_steps_created_by_foreign` (`created_by`),
  KEY `sales_process_steps_updated_by_foreign` (`updated_by`),
  KEY `sales_process_steps_assigned_to_foreign` (`assigned_to`),
  CONSTRAINT `sales_process_steps_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_process_steps_completed_by_foreign` FOREIGN KEY (`completed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_process_steps_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_process_steps_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_process_steps_sales_transaction_id_foreign` FOREIGN KEY (`sales_transaction_id`) REFERENCES `sales_transactions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_process_steps_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `sales_process_steps`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
