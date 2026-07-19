<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `bank_housing_partnerships` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `bank_kredit_id` bigint(20) unsigned NOT NULL,
  `bank_branch_id` bigint(20) unsigned DEFAULT NULL,
  `perumahan_id` bigint(20) unsigned NOT NULL,
  `agreement_number` varchar(255) NOT NULL,
  `agreement_name` varchar(255) NOT NULL,
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
  UNIQUE KEY `bank_housing_agreement_unique` (`bank_kredit_id`,`perumahan_id`,`agreement_number`),
  KEY `bank_housing_partnerships_bank_branch_id_foreign` (`bank_branch_id`),
  KEY `bank_housing_partnerships_perumahan_id_foreign` (`perumahan_id`),
  KEY `bank_housing_partnerships_locked_by_foreign` (`locked_by`),
  KEY `bank_housing_partnerships_record_status_index` (`record_status`),
  CONSTRAINT `bank_housing_partnerships_bank_branch_id_foreign` FOREIGN KEY (`bank_branch_id`) REFERENCES `bank_branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bank_housing_partnerships_bank_kredit_id_foreign` FOREIGN KEY (`bank_kredit_id`) REFERENCES `bank_kredits` (`id`),
  CONSTRAINT `bank_housing_partnerships_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bank_housing_partnerships_perumahan_id_foreign` FOREIGN KEY (`perumahan_id`) REFERENCES `perumahans` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `bank_housing_partnerships`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
