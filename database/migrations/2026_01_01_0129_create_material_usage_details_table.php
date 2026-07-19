<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `material_usage_details` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `material_usage_id` bigint(20) unsigned NOT NULL,
  `site_material_stock_id` bigint(20) unsigned NOT NULL,
  `barang_material_id` bigint(20) unsigned NOT NULL,
  `detail_rumah_hpp_item_id` bigint(20) unsigned DEFAULT NULL,
  `qty` decimal(16,2) NOT NULL,
  `satuan` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `material_usage_details_material_usage_id_foreign` (`material_usage_id`),
  KEY `material_usage_details_site_material_stock_id_foreign` (`site_material_stock_id`),
  KEY `material_usage_details_barang_material_id_foreign` (`barang_material_id`),
  KEY `material_usage_details_detail_rumah_hpp_item_id_foreign` (`detail_rumah_hpp_item_id`),
  CONSTRAINT `material_usage_details_barang_material_id_foreign` FOREIGN KEY (`barang_material_id`) REFERENCES `barang_materials` (`id`) ON DELETE CASCADE,
  CONSTRAINT `material_usage_details_detail_rumah_hpp_item_id_foreign` FOREIGN KEY (`detail_rumah_hpp_item_id`) REFERENCES `detail_rumah_hpp_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_usage_details_material_usage_id_foreign` FOREIGN KEY (`material_usage_id`) REFERENCES `material_usages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `material_usage_details_site_material_stock_id_foreign` FOREIGN KEY (`site_material_stock_id`) REFERENCES `site_material_stocks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `material_usage_details`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
