<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `bank_housing_partnership_versions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `bank_housing_partnership_id` bigint(20) unsigned NOT NULL,
  `version_number` int(10) unsigned NOT NULL,
  `agreement_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`agreement_snapshot`)),
  `effective_from` date NOT NULL,
  `effective_until` date DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bank_housing_partnership_version_unique` (`bank_housing_partnership_id`,`version_number`),
  KEY `bank_housing_partnership_versions_created_by_foreign` (`created_by`),
  CONSTRAINT `bank_housing_partnership_versions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bank_housing_version_partnership_fk` FOREIGN KEY (`bank_housing_partnership_id`) REFERENCES `bank_housing_partnerships` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `bank_housing_partnership_versions`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
