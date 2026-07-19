<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `developer_kpr_applications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `application_no` varchar(255) NOT NULL,
  `sales_transaction_id` bigint(20) unsigned NOT NULL,
  `developer_kpr_product_id` bigint(20) unsigned DEFAULT NULL,
  `product_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`product_snapshot`)),
  `financing_amount` decimal(18,2) NOT NULL,
  `tenor_months` smallint(5) unsigned NOT NULL,
  `estimated_installment` decimal(18,2) NOT NULL,
  `analysis_status` varchar(255) NOT NULL DEFAULT 'belum_dianalisis',
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `record_status` varchar(255) NOT NULL DEFAULT 'draft',
  `locked_at` timestamp NULL DEFAULT NULL,
  `locked_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `developer_kpr_applications_application_no_unique` (`application_no`),
  UNIQUE KEY `developer_kpr_applications_sales_transaction_id_unique` (`sales_transaction_id`),
  KEY `developer_kpr_applications_developer_kpr_product_id_foreign` (`developer_kpr_product_id`),
  KEY `developer_kpr_applications_locked_by_foreign` (`locked_by`),
  CONSTRAINT `developer_kpr_applications_developer_kpr_product_id_foreign` FOREIGN KEY (`developer_kpr_product_id`) REFERENCES `developer_kpr_products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `developer_kpr_applications_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `developer_kpr_applications_sales_transaction_id_foreign` FOREIGN KEY (`sales_transaction_id`) REFERENCES `sales_transactions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `developer_kpr_applications`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
