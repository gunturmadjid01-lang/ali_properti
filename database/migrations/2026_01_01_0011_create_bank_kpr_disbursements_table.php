<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `bank_kpr_disbursements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `disbursement_no` varchar(255) NOT NULL,
  `kpr_submission_id` bigint(20) unsigned NOT NULL,
  `master_bank_id` bigint(20) unsigned DEFAULT NULL,
  `disbursement_date` date NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `bank_reference` varchar(255) DEFAULT NULL,
  `proof_path` varchar(255) DEFAULT NULL,
  `proof_original_name` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `record_status` varchar(255) NOT NULL DEFAULT 'draft',
  `locked_at` timestamp NULL DEFAULT NULL,
  `locked_by` bigint(20) unsigned DEFAULT NULL,
  `customer_receipt_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bank_kpr_disbursements_disbursement_no_unique` (`disbursement_no`),
  KEY `bank_kpr_disbursements_kpr_submission_id_foreign` (`kpr_submission_id`),
  KEY `bank_kpr_disbursements_master_bank_id_foreign` (`master_bank_id`),
  KEY `bank_kpr_disbursements_locked_by_foreign` (`locked_by`),
  KEY `bank_kpr_disbursements_customer_receipt_id_foreign` (`customer_receipt_id`),
  KEY `bank_kpr_disbursements_created_by_foreign` (`created_by`),
  KEY `bank_kpr_disbursements_updated_by_foreign` (`updated_by`),
  CONSTRAINT `bank_kpr_disbursements_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bank_kpr_disbursements_customer_receipt_id_foreign` FOREIGN KEY (`customer_receipt_id`) REFERENCES `customer_receipts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bank_kpr_disbursements_kpr_submission_id_foreign` FOREIGN KEY (`kpr_submission_id`) REFERENCES `kpr_submissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bank_kpr_disbursements_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bank_kpr_disbursements_master_bank_id_foreign` FOREIGN KEY (`master_bank_id`) REFERENCES `master_banks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bank_kpr_disbursements_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `bank_kpr_disbursements`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
