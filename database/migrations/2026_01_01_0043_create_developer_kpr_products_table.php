<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `developer_kpr_products` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `cabang_perusahaan_id` bigint(20) unsigned DEFAULT NULL,
  `perumahan_id` bigint(20) unsigned DEFAULT NULL,
  `minimum_dp` decimal(18,2) NOT NULL DEFAULT 0.00,
  `dp_type` varchar(255) NOT NULL DEFAULT 'nominal',
  `maximum_financing` decimal(18,2) DEFAULT NULL,
  `financing_type` varchar(255) NOT NULL DEFAULT 'nominal',
  `financing_basis` varchar(255) NOT NULL DEFAULT 'final_price',
  `minimum_tenor_months` smallint(5) unsigned NOT NULL,
  `maximum_tenor_months` smallint(5) unsigned NOT NULL,
  `tenor_mode` varchar(255) NOT NULL DEFAULT 'range',
  `tenor_increment` smallint(5) unsigned NOT NULL DEFAULT 12,
  `allowed_tenors` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`allowed_tenors`)),
  `annual_margin` decimal(8,4) NOT NULL DEFAULT 0.0000,
  `margin_method` varchar(255) NOT NULL DEFAULT 'flat',
  `margin_scope` varchar(255) NOT NULL DEFAULT 'all',
  `administration_fee` decimal(18,2) NOT NULL DEFAULT 0.00,
  `contract_fee` decimal(18,2) NOT NULL DEFAULT 0.00,
  `grace_period_days` smallint(5) unsigned NOT NULL DEFAULT 0,
  `penalty_method` varchar(255) NOT NULL DEFAULT 'fixed',
  `penalty_value` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `minimum_income` decimal(18,2) NOT NULL DEFAULT 0.00,
  `maximum_age` smallint(5) unsigned DEFAULT NULL,
  `requirements` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`requirements`)),
  `handover_terms` text DEFAULT NULL,
  `effective_from` date NOT NULL,
  `effective_until` date DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `version` int(10) unsigned NOT NULL DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `unit_types` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`unit_types`)),
  `margin_tiers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`margin_tiers`)),
  `fees` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`fees`)),
  `schedule_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`schedule_config`)),
  `penalty_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`penalty_config`)),
  `eligibility_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`eligibility_config`)),
  `document_requirements` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`document_requirements`)),
  `handover_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`handover_config`)),
  `advanced_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`advanced_config`)),
  `record_status` varchar(255) NOT NULL DEFAULT 'draft',
  `locked_at` timestamp NULL DEFAULT NULL,
  `locked_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `developer_kpr_products_code_unique` (`code`),
  KEY `developer_kpr_products_cabang_perusahaan_id_foreign` (`cabang_perusahaan_id`),
  KEY `developer_kpr_products_perumahan_id_foreign` (`perumahan_id`),
  KEY `developer_kpr_products_created_by_foreign` (`created_by`),
  KEY `developer_kpr_products_locked_by_foreign` (`locked_by`),
  KEY `developer_kpr_products_record_status_index` (`record_status`),
  CONSTRAINT `developer_kpr_products_cabang_perusahaan_id_foreign` FOREIGN KEY (`cabang_perusahaan_id`) REFERENCES `cabang_perusahaans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `developer_kpr_products_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `developer_kpr_products_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `developer_kpr_products_perumahan_id_foreign` FOREIGN KEY (`perumahan_id`) REFERENCES `perumahans` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `developer_kpr_products`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
