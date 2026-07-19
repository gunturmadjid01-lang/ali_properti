<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `material_stock_opnames` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `kode_opname` varchar(255) NOT NULL,
  `gudang_id` bigint(20) unsigned NOT NULL,
  `tanggal` date NOT NULL,
  `keterangan` text DEFAULT NULL,
  `record_status` varchar(255) NOT NULL DEFAULT 'locked',
  `locked_at` timestamp NULL DEFAULT NULL,
  `locked_by` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `material_stock_opnames_kode_opname_unique` (`kode_opname`),
  KEY `material_stock_opnames_gudang_id_foreign` (`gudang_id`),
  KEY `material_stock_opnames_locked_by_foreign` (`locked_by`),
  KEY `material_stock_opnames_created_by_foreign` (`created_by`),
  KEY `material_stock_opnames_updated_by_foreign` (`updated_by`),
  CONSTRAINT `material_stock_opnames_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_stock_opnames_gudang_id_foreign` FOREIGN KEY (`gudang_id`) REFERENCES `gudangs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `material_stock_opnames_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_stock_opnames_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `material_stock_opnames`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
