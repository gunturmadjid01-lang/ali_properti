<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `bank_branches` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `bank_kredit_id` bigint(20) unsigned NOT NULL,
  `branch_code` varchar(255) NOT NULL,
  `branch_name` varchar(255) NOT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `pic_name` varchar(255) DEFAULT NULL,
  `pic_position` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'aktif',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `record_status` varchar(255) NOT NULL DEFAULT 'draft',
  `locked_at` timestamp NULL DEFAULT NULL,
  `locked_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bank_branches_bank_kredit_id_branch_code_unique` (`bank_kredit_id`,`branch_code`),
  KEY `bank_branches_locked_by_foreign` (`locked_by`),
  KEY `bank_branches_record_status_index` (`record_status`),
  CONSTRAINT `bank_branches_bank_kredit_id_foreign` FOREIGN KEY (`bank_kredit_id`) REFERENCES `bank_kredits` (`id`),
  CONSTRAINT `bank_branches_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `bank_branches`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
