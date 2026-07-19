<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `cash_installment_contracts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `contract_no` varchar(255) NOT NULL,
  `sales_transaction_id` bigint(20) unsigned NOT NULL,
  `cash_installment_scheme_id` bigint(20) unsigned DEFAULT NULL,
  `scheme_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`scheme_snapshot`)),
  `contract_value` decimal(18,2) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `start_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `record_status` varchar(255) NOT NULL DEFAULT 'draft',
  `locked_at` timestamp NULL DEFAULT NULL,
  `locked_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cash_installment_contracts_contract_no_unique` (`contract_no`),
  UNIQUE KEY `cash_installment_contracts_sales_transaction_id_unique` (`sales_transaction_id`),
  KEY `cash_installment_contracts_cash_installment_scheme_id_foreign` (`cash_installment_scheme_id`),
  KEY `cash_installment_contracts_locked_by_foreign` (`locked_by`),
  CONSTRAINT `cash_installment_contracts_cash_installment_scheme_id_foreign` FOREIGN KEY (`cash_installment_scheme_id`) REFERENCES `cash_installment_schemes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cash_installment_contracts_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cash_installment_contracts_sales_transaction_id_foreign` FOREIGN KEY (`sales_transaction_id`) REFERENCES `sales_transactions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `cash_installment_contracts`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
