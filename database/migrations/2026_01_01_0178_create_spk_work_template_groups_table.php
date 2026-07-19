<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `spk_work_template_groups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `spk_work_template_id` bigint(20) unsigned NOT NULL,
  `judul_tahapan` varchar(255) NOT NULL,
  `urutan` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `spk_work_template_groups_unique_title` (`spk_work_template_id`,`judul_tahapan`),
  CONSTRAINT `spk_work_template_groups_spk_work_template_id_foreign` FOREIGN KEY (`spk_work_template_id`) REFERENCES `spk_work_templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `spk_work_template_groups`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
