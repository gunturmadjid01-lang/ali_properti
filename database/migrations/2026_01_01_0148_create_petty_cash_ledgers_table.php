<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `petty_cash_ledgers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `petty_cash_account_id` bigint(20) unsigned NOT NULL,
  `transaction_date` date NOT NULL,
  `direction` varchar(255) NOT NULL,
  `amount` decimal(16,2) NOT NULL,
  `balance_after` decimal(16,2) NOT NULL,
  `source_type` varchar(255) DEFAULT NULL,
  `source_id` bigint(20) unsigned DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `petty_cash_ledgers_source_type_source_id_index` (`source_type`,`source_id`),
  KEY `petty_cash_ledgers_created_by_foreign` (`created_by`),
  KEY `petty_cash_ledgers_petty_cash_account_id_transaction_date_index` (`petty_cash_account_id`,`transaction_date`),
  CONSTRAINT `petty_cash_ledgers_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `petty_cash_ledgers_petty_cash_account_id_foreign` FOREIGN KEY (`petty_cash_account_id`) REFERENCES `petty_cash_accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `petty_cash_ledgers`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
