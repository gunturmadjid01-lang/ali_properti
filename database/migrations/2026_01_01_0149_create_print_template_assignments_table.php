<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `print_template_assignments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `print_template_id` bigint(20) unsigned NOT NULL,
  `print_key` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `print_template_assignments_print_key_unique` (`print_key`),
  KEY `print_template_assignments_print_template_id_foreign` (`print_template_id`),
  CONSTRAINT `print_template_assignments_print_template_id_foreign` FOREIGN KEY (`print_template_id`) REFERENCES `print_templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `print_template_assignments`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
