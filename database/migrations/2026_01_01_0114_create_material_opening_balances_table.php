<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `material_opening_balances` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `gudang_id` bigint(20) unsigned NOT NULL,
  `barang_material_id` bigint(20) unsigned NOT NULL,
  `tanggal_saldo` date NOT NULL,
  `qty` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `harga_satuan` decimal(16,2) NOT NULL DEFAULT 0.00,
  `total_nilai` decimal(18,2) NOT NULL DEFAULT 0.00,
  `catatan` text DEFAULT NULL,
  `record_status` varchar(255) NOT NULL DEFAULT 'draft',
  `locked_at` timestamp NULL DEFAULT NULL,
  `locked_by` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `input_qty` decimal(18,6) DEFAULT NULL,
  `input_unit_id` bigint(20) unsigned DEFAULT NULL,
  `input_unit_symbol` varchar(50) DEFAULT NULL,
  `conversion_to_base` decimal(18,6) NOT NULL DEFAULT 1.000000,
  PRIMARY KEY (`id`),
  UNIQUE KEY `material_opening_balance_unique` (`gudang_id`,`barang_material_id`),
  KEY `material_opening_balances_barang_material_id_foreign` (`barang_material_id`),
  KEY `material_opening_balances_locked_by_foreign` (`locked_by`),
  KEY `material_opening_balances_created_by_foreign` (`created_by`),
  KEY `material_opening_balances_updated_by_foreign` (`updated_by`),
  KEY `material_opening_balances_input_unit_id_foreign` (`input_unit_id`),
  CONSTRAINT `material_opening_balances_barang_material_id_foreign` FOREIGN KEY (`barang_material_id`) REFERENCES `barang_materials` (`id`) ON DELETE CASCADE,
  CONSTRAINT `material_opening_balances_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_opening_balances_gudang_id_foreign` FOREIGN KEY (`gudang_id`) REFERENCES `gudangs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `material_opening_balances_input_unit_id_foreign` FOREIGN KEY (`input_unit_id`) REFERENCES `material_units` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_opening_balances_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_opening_balances_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `material_opening_balances`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
