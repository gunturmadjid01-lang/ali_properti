<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `bank_credit_products` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `bank_kredit_id` bigint(20) unsigned NOT NULL,
  `bank_branch_id` bigint(20) unsigned DEFAULT NULL,
  `product_code` varchar(255) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `product_type` varchar(255) NOT NULL,
  `subsidy_type` varchar(255) NOT NULL DEFAULT 'non_subsidi',
  `scheme_type` varchar(255) NOT NULL DEFAULT 'konvensional',
  `minimum_ceiling` decimal(18,2) NOT NULL DEFAULT 0.00,
  `maximum_ceiling` decimal(18,2) NOT NULL DEFAULT 0.00,
  `minimum_down_payment` decimal(18,2) NOT NULL DEFAULT 0.00,
  `maximum_tenor_months` smallint(5) unsigned NOT NULL DEFAULT 1,
  `indicative_interest_margin` decimal(8,4) NOT NULL DEFAULT 0.0000,
  `provision_fee` decimal(18,2) NOT NULL DEFAULT 0.00,
  `administration_fee` decimal(18,2) NOT NULL DEFAULT 0.00,
  `appraisal_fee` decimal(18,2) NOT NULL DEFAULT 0.00,
  `insurance_fee` decimal(18,2) NOT NULL DEFAULT 0.00,
  `notary_fee` decimal(18,2) NOT NULL DEFAULT 0.00,
  `disbursement_method` varchar(255) NOT NULL,
  `estimated_sla_days` smallint(5) unsigned DEFAULT NULL,
  `effective_from` date NOT NULL,
  `effective_until` date DEFAULT NULL,
  `current_version` int(10) unsigned NOT NULL DEFAULT 1,
  `status` varchar(255) NOT NULL DEFAULT 'aktif',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `record_status` varchar(255) NOT NULL DEFAULT 'draft',
  `locked_at` timestamp NULL DEFAULT NULL,
  `locked_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bank_credit_products_product_code_unique` (`product_code`),
  KEY `bank_credit_products_bank_kredit_id_foreign` (`bank_kredit_id`),
  KEY `bank_credit_products_bank_branch_id_foreign` (`bank_branch_id`),
  KEY `bank_credit_products_locked_by_foreign` (`locked_by`),
  KEY `bank_credit_products_record_status_index` (`record_status`),
  CONSTRAINT `bank_credit_products_bank_branch_id_foreign` FOREIGN KEY (`bank_branch_id`) REFERENCES `bank_branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bank_credit_products_bank_kredit_id_foreign` FOREIGN KEY (`bank_kredit_id`) REFERENCES `bank_kredits` (`id`),
  CONSTRAINT `bank_credit_products_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `bank_credit_products`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
