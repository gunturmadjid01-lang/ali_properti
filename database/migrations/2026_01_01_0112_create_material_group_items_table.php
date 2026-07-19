<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `material_group_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `material_group_id` bigint(20) unsigned NOT NULL,
  `barang_material_id` bigint(20) unsigned NOT NULL,
  `material_unit_id` bigint(20) unsigned NOT NULL,
  `quantity` decimal(18,6) NOT NULL,
  `conversion_to_base` decimal(18,6) NOT NULL DEFAULT 1.000000,
  `quantity_base` decimal(18,6) NOT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `material_group_items_barang_material_id_foreign` (`barang_material_id`),
  KEY `material_group_items_material_unit_id_foreign` (`material_unit_id`),
  KEY `material_group_items_material_group_id_sort_order_index` (`material_group_id`,`sort_order`),
  CONSTRAINT `material_group_items_barang_material_id_foreign` FOREIGN KEY (`barang_material_id`) REFERENCES `barang_materials` (`id`),
  CONSTRAINT `material_group_items_material_group_id_foreign` FOREIGN KEY (`material_group_id`) REFERENCES `material_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `material_group_items_material_unit_id_foreign` FOREIGN KEY (`material_unit_id`) REFERENCES `material_units` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `material_group_items`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
