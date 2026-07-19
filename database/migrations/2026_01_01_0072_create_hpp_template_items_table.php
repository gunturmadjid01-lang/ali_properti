<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `hpp_template_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `hpp_template_stage_id` bigint(20) unsigned NOT NULL,
  `kelompok_hpp_id` bigint(20) unsigned NOT NULL,
  `nama_pekerjaan` varchar(255) NOT NULL,
  `volume` decimal(16,2) NOT NULL DEFAULT 0.00,
  `satuan` varchar(255) NOT NULL DEFAULT '',
  `urutan` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hpp_template_items_hpp_template_stage_id_foreign` (`hpp_template_stage_id`),
  KEY `hpp_template_items_kelompok_hpp_id_foreign` (`kelompok_hpp_id`),
  CONSTRAINT `hpp_template_items_hpp_template_stage_id_foreign` FOREIGN KEY (`hpp_template_stage_id`) REFERENCES `hpp_template_stages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hpp_template_items_kelompok_hpp_id_foreign` FOREIGN KEY (`kelompok_hpp_id`) REFERENCES `kelompok_hpps` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `hpp_template_items`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
