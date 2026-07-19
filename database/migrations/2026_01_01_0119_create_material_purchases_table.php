<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `material_purchases` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `gudang_id` bigint(20) unsigned DEFAULT NULL,
  `kode_pembelian` varchar(255) NOT NULL,
  `tanggal` date NOT NULL,
  `tanggal_barang_masuk` date DEFAULT NULL,
  `material_request_id` bigint(20) unsigned DEFAULT NULL,
  `material_purchase_request_id` bigint(20) unsigned DEFAULT NULL,
  `perumahan_id` bigint(20) unsigned DEFAULT NULL,
  `detail_rumah_id` bigint(20) unsigned DEFAULT NULL,
  `tahapan_pembangunan_id` bigint(20) unsigned DEFAULT NULL,
  `kelompok_hpp_id` bigint(20) unsigned DEFAULT NULL,
  `supplier_id` bigint(20) unsigned DEFAULT NULL,
  `supplier` varchar(255) DEFAULT NULL,
  `metode_pembayaran` varchar(255) NOT NULL DEFAULT 'tunai',
  `planned_master_bank_id` bigint(20) unsigned DEFAULT NULL,
  `payment_master_bank_id` bigint(20) unsigned DEFAULT NULL,
  `total_nominal` decimal(16,2) NOT NULL DEFAULT 0.00,
  `subtotal_nominal` decimal(16,2) NOT NULL DEFAULT 0.00,
  `diskon_transaksi` decimal(16,2) NOT NULL DEFAULT 0.00,
  `status` varchar(255) NOT NULL DEFAULT 'menunggu_approval_manager',
  `keterangan` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `fund_released_by` bigint(20) unsigned DEFAULT NULL,
  `fund_released_at` timestamp NULL DEFAULT NULL,
  `received_by` bigint(20) unsigned DEFAULT NULL,
  `received_at` timestamp NULL DEFAULT NULL,
  `receive_note` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `record_status` varchar(255) NOT NULL DEFAULT 'draft',
  `locked_at` timestamp NULL DEFAULT NULL,
  `locked_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `material_purchases_kode_pembelian_unique` (`kode_pembelian`),
  KEY `material_purchases_material_request_id_foreign` (`material_request_id`),
  KEY `material_purchases_perumahan_id_foreign` (`perumahan_id`),
  KEY `material_purchases_detail_rumah_id_foreign` (`detail_rumah_id`),
  KEY `material_purchases_tahapan_pembangunan_id_foreign` (`tahapan_pembangunan_id`),
  KEY `material_purchases_kelompok_hpp_id_foreign` (`kelompok_hpp_id`),
  KEY `material_purchases_created_by_foreign` (`created_by`),
  KEY `material_purchases_approved_by_foreign` (`approved_by`),
  KEY `material_purchases_fund_released_by_foreign` (`fund_released_by`),
  KEY `material_purchases_received_by_foreign` (`received_by`),
  KEY `material_purchases_gudang_id_foreign` (`gudang_id`),
  KEY `material_purchases_locked_by_foreign` (`locked_by`),
  KEY `material_purchases_updated_by_foreign` (`updated_by`),
  KEY `material_purchases_planned_master_bank_id_foreign` (`planned_master_bank_id`),
  KEY `material_purchases_payment_master_bank_id_foreign` (`payment_master_bank_id`),
  KEY `material_purchases_supplier_id_foreign` (`supplier_id`),
  KEY `material_purchases_material_purchase_request_id_foreign` (`material_purchase_request_id`),
  CONSTRAINT `material_purchases_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_purchases_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_purchases_detail_rumah_id_foreign` FOREIGN KEY (`detail_rumah_id`) REFERENCES `detail_rumahs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_purchases_fund_released_by_foreign` FOREIGN KEY (`fund_released_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_purchases_gudang_id_foreign` FOREIGN KEY (`gudang_id`) REFERENCES `gudangs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_purchases_kelompok_hpp_id_foreign` FOREIGN KEY (`kelompok_hpp_id`) REFERENCES `kelompok_hpps` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_purchases_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_purchases_material_purchase_request_id_foreign` FOREIGN KEY (`material_purchase_request_id`) REFERENCES `material_purchase_requests` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_purchases_material_request_id_foreign` FOREIGN KEY (`material_request_id`) REFERENCES `material_requests` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_purchases_payment_master_bank_id_foreign` FOREIGN KEY (`payment_master_bank_id`) REFERENCES `master_banks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_purchases_perumahan_id_foreign` FOREIGN KEY (`perumahan_id`) REFERENCES `perumahans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_purchases_planned_master_bank_id_foreign` FOREIGN KEY (`planned_master_bank_id`) REFERENCES `master_banks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_purchases_received_by_foreign` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_purchases_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_purchases_tahapan_pembangunan_id_foreign` FOREIGN KEY (`tahapan_pembangunan_id`) REFERENCES `tahapan_pembangunans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_purchases_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `material_purchases`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
