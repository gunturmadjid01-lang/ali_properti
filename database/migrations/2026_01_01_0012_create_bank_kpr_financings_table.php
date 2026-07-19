<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `bank_kpr_financings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `kpr_submission_id` bigint(20) unsigned NOT NULL,
  `sale_price` decimal(18,2) NOT NULL,
  `approved_limit` decimal(18,2) NOT NULL DEFAULT 0.00,
  `tenor_months` smallint(5) unsigned DEFAULT NULL,
  `interest_rate` decimal(8,4) DEFAULT NULL,
  `booking_fee` decimal(18,2) NOT NULL DEFAULT 0.00,
  `down_payment` decimal(18,2) NOT NULL DEFAULT 0.00,
  `shortfall` decimal(18,2) NOT NULL DEFAULT 0.00,
  `developer_fee` decimal(18,2) NOT NULL DEFAULT 0.00,
  `notary_fee` decimal(18,2) NOT NULL DEFAULT 0.00,
  `expected_disbursement_date` date DEFAULT NULL,
  `sp3k_number` varchar(255) DEFAULT NULL,
  `sp3k_date` date DEFAULT NULL,
  `sp3k_expired_at` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `record_status` varchar(255) NOT NULL DEFAULT 'draft',
  `locked_at` timestamp NULL DEFAULT NULL,
  `locked_by` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bank_kpr_financings_kpr_submission_id_unique` (`kpr_submission_id`),
  KEY `bank_kpr_financings_locked_by_foreign` (`locked_by`),
  KEY `bank_kpr_financings_created_by_foreign` (`created_by`),
  KEY `bank_kpr_financings_updated_by_foreign` (`updated_by`),
  CONSTRAINT `bank_kpr_financings_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bank_kpr_financings_kpr_submission_id_foreign` FOREIGN KEY (`kpr_submission_id`) REFERENCES `kpr_submissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bank_kpr_financings_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bank_kpr_financings_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `bank_kpr_financings`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
