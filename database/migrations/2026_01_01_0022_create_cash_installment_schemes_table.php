<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `cash_installment_schemes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `cabang_perusahaan_id` bigint(20) unsigned DEFAULT NULL,
  `perumahan_id` bigint(20) unsigned DEFAULT NULL,
  `minimum_booking_fee` decimal(18,2) NOT NULL DEFAULT 0.00,
  `booking_fee_deducts` varchar(255) NOT NULL DEFAULT 'down_payment',
  `minimum_dp` decimal(18,2) NOT NULL DEFAULT 0.00,
  `dp_type` varchar(255) NOT NULL DEFAULT 'nominal',
  `installment_count` smallint(5) unsigned NOT NULL,
  `maximum_tenor_months` smallint(5) unsigned NOT NULL,
  `payment_model` varchar(255) NOT NULL DEFAULT 'equal_monthly',
  `interval_type` varchar(255) NOT NULL DEFAULT 'monthly',
  `grace_period_days` smallint(5) unsigned NOT NULL DEFAULT 0,
  `penalty_method` varchar(255) NOT NULL DEFAULT 'fixed',
  `penalty_value` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `effective_from` date NOT NULL,
  `effective_until` date DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `version` int(10) unsigned NOT NULL DEFAULT 1,
  `requirements` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`requirements`)),
  `handover_terms` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `unit_types` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`unit_types`)),
  `schedule_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`schedule_config`)),
  `penalty_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`penalty_config`)),
  `handover_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`handover_config`)),
  `document_requirements` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`document_requirements`)),
  `advanced_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`advanced_config`)),
  `record_status` varchar(255) NOT NULL DEFAULT 'draft',
  `locked_at` timestamp NULL DEFAULT NULL,
  `locked_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cash_installment_schemes_code_unique` (`code`),
  KEY `cash_installment_schemes_cabang_perusahaan_id_foreign` (`cabang_perusahaan_id`),
  KEY `cash_installment_schemes_perumahan_id_foreign` (`perumahan_id`),
  KEY `cash_installment_schemes_created_by_foreign` (`created_by`),
  KEY `cash_installment_schemes_locked_by_foreign` (`locked_by`),
  KEY `cash_installment_schemes_record_status_index` (`record_status`),
  CONSTRAINT `cash_installment_schemes_cabang_perusahaan_id_foreign` FOREIGN KEY (`cabang_perusahaan_id`) REFERENCES `cabang_perusahaans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cash_installment_schemes_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cash_installment_schemes_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cash_installment_schemes_perumahan_id_foreign` FOREIGN KEY (`perumahan_id`) REFERENCES `perumahans` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `cash_installment_schemes`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
