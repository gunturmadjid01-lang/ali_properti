<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `print_templates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `paper_size` enum('a4','legal','custom') NOT NULL DEFAULT 'a4',
  `orientation` enum('portrait','landscape') NOT NULL DEFAULT 'portrait',
  `custom_width_mm` decimal(8,2) DEFAULT NULL,
  `custom_height_mm` decimal(8,2) DEFAULT NULL,
  `margin_top_mm` decimal(8,2) NOT NULL DEFAULT 15.00,
  `margin_right_mm` decimal(8,2) NOT NULL DEFAULT 15.00,
  `margin_bottom_mm` decimal(8,2) NOT NULL DEFAULT 15.00,
  `margin_left_mm` decimal(8,2) NOT NULL DEFAULT 15.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `print_templates`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
