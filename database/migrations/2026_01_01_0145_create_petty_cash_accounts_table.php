<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `petty_cash_accounts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `assigned_user_id` bigint(20) unsigned DEFAULT NULL,
  `target_amount` decimal(16,2) NOT NULL DEFAULT 0.00,
  `balance` decimal(16,2) NOT NULL DEFAULT 0.00,
  `minimum_balance` decimal(16,2) NOT NULL DEFAULT 0.00,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `petty_cash_accounts_code_unique` (`code`),
  KEY `petty_cash_accounts_branch_id_foreign` (`branch_id`),
  KEY `petty_cash_accounts_created_by_foreign` (`created_by`),
  KEY `petty_cash_accounts_updated_by_foreign` (`updated_by`),
  KEY `petty_cash_accounts_assigned_user_id_status_index` (`assigned_user_id`,`status`),
  CONSTRAINT `petty_cash_accounts_assigned_user_id_foreign` FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `petty_cash_accounts_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `cabang_perusahaans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `petty_cash_accounts_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `petty_cash_accounts_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `petty_cash_accounts`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
