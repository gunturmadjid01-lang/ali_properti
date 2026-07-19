<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `spk_work_template_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `spk_work_template_group_id` bigint(20) unsigned NOT NULL,
  `nama_pekerjaan` varchar(255) NOT NULL,
  `volume` decimal(16,2) NOT NULL DEFAULT 0.00,
  `satuan` varchar(255) NOT NULL DEFAULT '',
  `harga_satuan` decimal(18,2) NOT NULL DEFAULT 0.00,
  `urutan` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `spk_work_template_items_spk_work_template_group_id_foreign` (`spk_work_template_group_id`),
  CONSTRAINT `spk_work_template_items_spk_work_template_group_id_foreign` FOREIGN KEY (`spk_work_template_group_id`) REFERENCES `spk_work_template_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `spk_work_template_items`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
