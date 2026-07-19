<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `cash_installment_scheme_steps` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cash_installment_scheme_id` bigint(20) unsigned NOT NULL,
  `sequence` smallint(5) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `calculation_type` varchar(255) NOT NULL,
  `value` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `due_offset_months` smallint(5) unsigned NOT NULL DEFAULT 0,
  `required_before` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cash_scheme_step_sequence_unique` (`cash_installment_scheme_id`,`sequence`),
  KEY `cash_installment_scheme_steps_created_by_foreign` (`created_by`),
  CONSTRAINT `cash_installment_scheme_steps_cash_installment_scheme_id_foreign` FOREIGN KEY (`cash_installment_scheme_id`) REFERENCES `cash_installment_schemes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cash_installment_scheme_steps_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `cash_installment_scheme_steps`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
