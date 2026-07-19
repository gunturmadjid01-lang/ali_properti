<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `site_schedule_allocations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `site_schedule_id` bigint(20) unsigned NOT NULL,
  `periode_ke` int(10) unsigned NOT NULL,
  `label_periode` varchar(255) DEFAULT NULL,
  `bobot_persen` decimal(8,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `site_schedule_allocations_site_schedule_id_periode_ke_unique` (`site_schedule_id`,`periode_ke`),
  CONSTRAINT `site_schedule_allocations_site_schedule_id_foreign` FOREIGN KEY (`site_schedule_id`) REFERENCES `site_schedules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `site_schedule_allocations`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
