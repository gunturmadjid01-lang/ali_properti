<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `sales_method_attempts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sales_transaction_id` bigint(20) unsigned NOT NULL,
  `attempt_no` int(10) unsigned NOT NULL,
  `payment_method` varchar(255) NOT NULL,
  `bank_kredit_id` bigint(20) unsigned DEFAULT NULL,
  `bank_credit_product_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'in_progress',
  `current_stage` varchar(255) DEFAULT NULL,
  `outcome` varchar(255) DEFAULT NULL,
  `failure_category` varchar(255) DEFAULT NULL,
  `failure_reason` text DEFAULT NULL,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ended_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sales_method_attempts_sales_transaction_id_attempt_no_unique` (`sales_transaction_id`,`attempt_no`),
  KEY `sales_method_attempts_bank_kredit_id_foreign` (`bank_kredit_id`),
  KEY `sales_method_attempts_bank_credit_product_id_foreign` (`bank_credit_product_id`),
  KEY `sales_method_attempts_created_by_foreign` (`created_by`),
  CONSTRAINT `sales_method_attempts_bank_credit_product_id_foreign` FOREIGN KEY (`bank_credit_product_id`) REFERENCES `bank_credit_products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_method_attempts_bank_kredit_id_foreign` FOREIGN KEY (`bank_kredit_id`) REFERENCES `bank_kredits` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_method_attempts_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_method_attempts_sales_transaction_id_foreign` FOREIGN KEY (`sales_transaction_id`) REFERENCES `sales_transactions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `sales_method_attempts`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
