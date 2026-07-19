<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `petty_cash_fundings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `petty_cash_account_id` bigint(20) unsigned NOT NULL,
  `number` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL,
  `request_date` date NOT NULL,
  `amount` decimal(16,2) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `record_status` varchar(255) NOT NULL DEFAULT 'draft',
  `request_notes` text DEFAULT NULL,
  `request_proof_path` varchar(255) DEFAULT NULL,
  `requested_by` bigint(20) unsigned DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `locked_at` timestamp NULL DEFAULT NULL,
  `locked_by` bigint(20) unsigned DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `approval_proof_path` varchar(255) DEFAULT NULL,
  `approval_notes` text DEFAULT NULL,
  `rejection_notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `petty_cash_fundings_number_unique` (`number`),
  KEY `petty_cash_fundings_requested_by_foreign` (`requested_by`),
  KEY `petty_cash_fundings_approved_by_foreign` (`approved_by`),
  KEY `petty_cash_fundings_petty_cash_account_id_status_index` (`petty_cash_account_id`,`status`),
  KEY `petty_cash_fundings_locked_by_foreign` (`locked_by`),
  CONSTRAINT `petty_cash_fundings_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `petty_cash_fundings_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `petty_cash_fundings_petty_cash_account_id_foreign` FOREIGN KEY (`petty_cash_account_id`) REFERENCES `petty_cash_accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `petty_cash_fundings_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `petty_cash_fundings`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
