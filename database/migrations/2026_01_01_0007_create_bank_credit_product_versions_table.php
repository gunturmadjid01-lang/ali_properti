<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `bank_credit_product_versions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `bank_credit_product_id` bigint(20) unsigned NOT NULL,
  `version_number` int(10) unsigned NOT NULL,
  `terms_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`terms_snapshot`)),
  `effective_from` date NOT NULL,
  `effective_until` date DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bank_credit_product_version_unique` (`bank_credit_product_id`,`version_number`),
  KEY `bank_credit_product_versions_created_by_foreign` (`created_by`),
  CONSTRAINT `bank_credit_product_versions_bank_credit_product_id_foreign` FOREIGN KEY (`bank_credit_product_id`) REFERENCES `bank_credit_products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bank_credit_product_versions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `bank_credit_product_versions`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
