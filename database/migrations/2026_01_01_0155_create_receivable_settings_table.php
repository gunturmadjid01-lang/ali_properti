<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `receivable_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `perumahan_id` bigint(20) unsigned DEFAULT NULL,
  `payment_method` varchar(255) DEFAULT NULL,
  `warning_days` smallint(5) unsigned NOT NULL DEFAULT 14,
  `urgent_days` smallint(5) unsigned NOT NULL DEFAULT 3,
  `issue_days_before_due` smallint(5) unsigned NOT NULL DEFAULT 14,
  `grace_period_days` smallint(5) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `receivable_setting_scope_unique` (`perumahan_id`,`payment_method`),
  CONSTRAINT `receivable_settings_perumahan_id_foreign` FOREIGN KEY (`perumahan_id`) REFERENCES `perumahans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `receivable_settings`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
