<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `customer_receipts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `receipt_no` varchar(255) NOT NULL,
  `sales_transaction_id` bigint(20) unsigned NOT NULL,
  `master_bank_id` bigint(20) unsigned DEFAULT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `payment_method` varchar(255) NOT NULL,
  `receipt_purpose` varchar(255) NOT NULL DEFAULT 'invoice_payment',
  `bank_reference` varchar(255) DEFAULT NULL,
  `sender_bank` varchar(255) DEFAULT NULL,
  `sender_name` varchar(255) DEFAULT NULL,
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
  `financial_transaction_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `customer_receipts_receipt_no_unique` (`receipt_no`),
  KEY `customer_receipts_sales_transaction_id_foreign` (`sales_transaction_id`),
  KEY `customer_receipts_master_bank_id_foreign` (`master_bank_id`),
  KEY `customer_receipts_locked_by_foreign` (`locked_by`),
  KEY `customer_receipts_approved_by_foreign` (`approved_by`),
  KEY `customer_receipts_journal_id_foreign` (`journal_id`),
  KEY `customer_receipts_financial_transaction_id_foreign` (`financial_transaction_id`),
  KEY `customer_receipts_created_by_foreign` (`created_by`),
  KEY `customer_receipts_updated_by_foreign` (`updated_by`),
  CONSTRAINT `customer_receipts_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customer_receipts_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customer_receipts_financial_transaction_id_foreign` FOREIGN KEY (`financial_transaction_id`) REFERENCES `transaksi_keuangans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customer_receipts_journal_id_foreign` FOREIGN KEY (`journal_id`) REFERENCES `journals` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customer_receipts_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customer_receipts_master_bank_id_foreign` FOREIGN KEY (`master_bank_id`) REFERENCES `master_banks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customer_receipts_sales_transaction_id_foreign` FOREIGN KEY (`sales_transaction_id`) REFERENCES `sales_transactions` (`id`),
  CONSTRAINT `customer_receipts_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `customer_receipts`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
