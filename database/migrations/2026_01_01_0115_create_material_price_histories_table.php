<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `material_price_histories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `barang_material_id` bigint(20) unsigned NOT NULL,
  `tanggal_berlaku` date NOT NULL,
  `harga_satuan` decimal(16,2) NOT NULL,
  `supplier` varchar(255) DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'aktif',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `record_status` varchar(255) NOT NULL DEFAULT 'draft',
  `locked_at` timestamp NULL DEFAULT NULL,
  `locked_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `material_price_histories_barang_material_id_foreign` (`barang_material_id`),
  KEY `material_price_histories_created_by_foreign` (`created_by`),
  KEY `material_price_histories_locked_by_foreign` (`locked_by`),
  KEY `material_price_histories_updated_by_foreign` (`updated_by`),
  CONSTRAINT `material_price_histories_barang_material_id_foreign` FOREIGN KEY (`barang_material_id`) REFERENCES `barang_materials` (`id`) ON DELETE CASCADE,
  CONSTRAINT `material_price_histories_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_price_histories_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_price_histories_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `material_price_histories`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
