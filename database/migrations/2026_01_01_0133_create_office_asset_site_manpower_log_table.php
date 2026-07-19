<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `office_asset_site_manpower_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `site_manpower_log_id` bigint(20) unsigned NOT NULL,
  `office_asset_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `manpower_asset_unique` (`site_manpower_log_id`,`office_asset_id`),
  KEY `office_asset_site_manpower_log_office_asset_id_foreign` (`office_asset_id`),
  CONSTRAINT `office_asset_site_manpower_log_office_asset_id_foreign` FOREIGN KEY (`office_asset_id`) REFERENCES `office_assets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `office_asset_site_manpower_log_site_manpower_log_id_foreign` FOREIGN KEY (`site_manpower_log_id`) REFERENCES `site_manpower_logs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `office_asset_site_manpower_log`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
