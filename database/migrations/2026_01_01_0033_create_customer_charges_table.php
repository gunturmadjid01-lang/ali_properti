<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `customer_charges` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `charge_no` varchar(255) NOT NULL,
  `sales_transaction_id` bigint(20) unsigned NOT NULL,
  `master_bank_id` bigint(20) unsigned DEFAULT NULL,
  `charge_type` varchar(255) NOT NULL,
  `category` varchar(255) NOT NULL,
  `description` varchar(255) NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `charge_date` date NOT NULL,
  `due_date` date NOT NULL,
  `paid_to` varchar(255) DEFAULT NULL,
  `payment_reference` varchar(255) DEFAULT NULL,
  `proof_path` varchar(255) DEFAULT NULL,
  `proof_original_name` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `record_status` varchar(255) NOT NULL DEFAULT 'draft',
  `locked_at` timestamp NULL DEFAULT NULL,
  `locked_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `journal_id` bigint(20) unsigned DEFAULT NULL,
  `reversal_status` varchar(255) DEFAULT NULL,
  `reversal_reason` text DEFAULT NULL,
  `reversed_at` timestamp NULL DEFAULT NULL,
  `reversed_by` bigint(20) unsigned DEFAULT NULL,
  `reversal_journal_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `customer_charges_charge_no_unique` (`charge_no`),
  KEY `customer_charges_master_bank_id_foreign` (`master_bank_id`),
  KEY `customer_charges_locked_by_foreign` (`locked_by`),
  KEY `customer_charges_approved_by_foreign` (`approved_by`),
  KEY `customer_charges_journal_id_foreign` (`journal_id`),
  KEY `customer_charges_reversed_by_foreign` (`reversed_by`),
  KEY `customer_charges_reversal_journal_id_foreign` (`reversal_journal_id`),
  KEY `customer_charges_created_by_foreign` (`created_by`),
  KEY `customer_charges_updated_by_foreign` (`updated_by`),
  KEY `customer_charges_status_due_date_index` (`status`,`due_date`),
  KEY `customer_charges_sales_transaction_id_charge_type_index` (`sales_transaction_id`,`charge_type`),
  CONSTRAINT `customer_charges_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customer_charges_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customer_charges_journal_id_foreign` FOREIGN KEY (`journal_id`) REFERENCES `journals` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customer_charges_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customer_charges_master_bank_id_foreign` FOREIGN KEY (`master_bank_id`) REFERENCES `master_banks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customer_charges_reversal_journal_id_foreign` FOREIGN KEY (`reversal_journal_id`) REFERENCES `journals` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customer_charges_reversed_by_foreign` FOREIGN KEY (`reversed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customer_charges_sales_transaction_id_foreign` FOREIGN KEY (`sales_transaction_id`) REFERENCES `sales_transactions` (`id`),
  CONSTRAINT `customer_charges_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `customer_charges`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
