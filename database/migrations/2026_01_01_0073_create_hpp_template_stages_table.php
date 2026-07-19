<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `hpp_template_stages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `konteks` varchar(255) NOT NULL,
  `nama_tahapan` varchar(255) NOT NULL,
  `bobot_persen` decimal(5,2) NOT NULL DEFAULT 0.00,
  `urutan` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hpp_template_stages_konteks_nama_tahapan_unique` (`konteks`,`nama_tahapan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `hpp_template_stages`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
