<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `sales_resolution_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `request_no` varchar(255) NOT NULL,
  `sales_transaction_id` bigint(20) unsigned NOT NULL,
  `spr_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `failed_stage` varchar(255) DEFAULT NULL,
  `failure_category` varchar(255) NOT NULL,
  `failure_reason` text NOT NULL,
  `proposed_payment_method` varchar(255) DEFAULT NULL,
  `restart_stage` varchar(255) DEFAULT NULL,
  `financial_treatment` varchar(255) NOT NULL DEFAULT 'review_required',
  `resolution_notes` text DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `record_status` varchar(255) NOT NULL DEFAULT 'draft',
  `locked_at` timestamp NULL DEFAULT NULL,
  `locked_by` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `applied_by` bigint(20) unsigned DEFAULT NULL,
  `applied_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sales_resolution_requests_request_no_unique` (`request_no`),
  KEY `sales_resolution_requests_sales_transaction_id_foreign` (`sales_transaction_id`),
  KEY `sales_resolution_requests_spr_id_foreign` (`spr_id`),
  KEY `sales_resolution_requests_locked_by_foreign` (`locked_by`),
  KEY `sales_resolution_requests_created_by_foreign` (`created_by`),
  KEY `sales_resolution_requests_applied_by_foreign` (`applied_by`),
  KEY `sales_resolution_requests_status_action_index` (`status`,`action`),
  CONSTRAINT `sales_resolution_requests_applied_by_foreign` FOREIGN KEY (`applied_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_resolution_requests_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_resolution_requests_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_resolution_requests_sales_transaction_id_foreign` FOREIGN KEY (`sales_transaction_id`) REFERENCES `sales_transactions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_resolution_requests_spr_id_foreign` FOREIGN KEY (`spr_id`) REFERENCES `sprs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `sales_resolution_requests`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
