<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `material_unit_conversions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `barang_material_id` bigint(20) unsigned NOT NULL,
  `level` tinyint(3) unsigned NOT NULL,
  `parent_unit_id` bigint(20) unsigned NOT NULL,
  `child_unit_id` bigint(20) unsigned NOT NULL,
  `factor` decimal(18,6) NOT NULL,
  `cumulative_factor` decimal(18,6) NOT NULL,
  `parent_price` decimal(18,2) NOT NULL DEFAULT 0.00,
  `child_price` decimal(18,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `material_unit_conversions_barang_material_id_level_unique` (`barang_material_id`,`level`),
  UNIQUE KEY `material_conversion_child_unique` (`barang_material_id`,`child_unit_id`),
  KEY `material_unit_conversions_parent_unit_id_foreign` (`parent_unit_id`),
  KEY `material_unit_conversions_child_unit_id_foreign` (`child_unit_id`),
  CONSTRAINT `material_unit_conversions_barang_material_id_foreign` FOREIGN KEY (`barang_material_id`) REFERENCES `barang_materials` (`id`) ON DELETE CASCADE,
  CONSTRAINT `material_unit_conversions_child_unit_id_foreign` FOREIGN KEY (`child_unit_id`) REFERENCES `material_units` (`id`),
  CONSTRAINT `material_unit_conversions_parent_unit_id_foreign` FOREIGN KEY (`parent_unit_id`) REFERENCES `material_units` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `material_unit_conversions`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
