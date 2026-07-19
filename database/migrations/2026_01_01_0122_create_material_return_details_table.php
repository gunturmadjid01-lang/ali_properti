<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `material_return_details` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `material_return_id` bigint(20) unsigned NOT NULL,
  `site_material_stock_id` bigint(20) unsigned NOT NULL,
  `barang_material_id` bigint(20) unsigned NOT NULL,
  `qty` decimal(16,2) NOT NULL,
  `satuan` varchar(255) NOT NULL,
  `harga_satuan` decimal(16,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(16,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `material_return_details_material_return_id_foreign` (`material_return_id`),
  KEY `material_return_details_site_material_stock_id_foreign` (`site_material_stock_id`),
  KEY `material_return_details_barang_material_id_foreign` (`barang_material_id`),
  CONSTRAINT `material_return_details_barang_material_id_foreign` FOREIGN KEY (`barang_material_id`) REFERENCES `barang_materials` (`id`) ON DELETE CASCADE,
  CONSTRAINT `material_return_details_material_return_id_foreign` FOREIGN KEY (`material_return_id`) REFERENCES `material_returns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `material_return_details_site_material_stock_id_foreign` FOREIGN KEY (`site_material_stock_id`) REFERENCES `site_material_stocks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `material_return_details`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
