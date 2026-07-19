<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `sprs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `kode_spr` varchar(255) NOT NULL,
  `revision_no` int(10) unsigned NOT NULL DEFAULT 0,
  `revision_status` varchar(255) NOT NULL DEFAULT 'current',
  `superseded_by_spr_id` bigint(20) unsigned DEFAULT NULL,
  `costumer_id` bigint(20) unsigned NOT NULL,
  `detail_rumah_id` bigint(20) unsigned NOT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `tanggal_spr` date NOT NULL,
  `booking_expires_at` datetime DEFAULT NULL,
  `metode_pembayaran` varchar(255) NOT NULL,
  `bank_kredit_id` bigint(20) unsigned DEFAULT NULL,
  `bank_branch_id` bigint(20) unsigned DEFAULT NULL,
  `kpr_tenor_bulan` smallint(5) unsigned DEFAULT NULL,
  `kpr_bunga_tahunan` decimal(5,2) DEFAULT NULL,
  `skema_bertahap` varchar(255) NOT NULL DEFAULT 'cash_bertahap',
  `harga_jual` decimal(18,2) NOT NULL DEFAULT 0.00,
  `booking_fee` decimal(18,2) NOT NULL DEFAULT 0.00,
  `booking_fee_includes_dp` tinyint(1) NOT NULL DEFAULT 0,
  `tanggal_pembayaran_booking_fee` date DEFAULT NULL,
  `uang_muka` decimal(18,2) NOT NULL DEFAULT 0.00,
  `uang_muka_jumlah_pembayaran` smallint(5) unsigned DEFAULT NULL,
  `tanggal_jatuh_tempo_dp` date DEFAULT NULL,
  `tanggal_jatuh_tempo_angsuran` date DEFAULT NULL,
  `nilai_pengajuan_kpr` decimal(18,2) NOT NULL DEFAULT 0.00,
  `penambahan_tanah` text DEFAULT NULL,
  `harga_penambahan_tanah` decimal(18,2) NOT NULL DEFAULT 0.00,
  `penambahan_lain_lain` text DEFAULT NULL,
  `harga_penambahan_lain_lain` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_penambahan_tanah` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_penambahan_lain_lain` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_penambahan` decimal(18,2) NOT NULL DEFAULT 0.00,
  `nilai_pengajuan_akhir` decimal(18,2) NOT NULL DEFAULT 0.00,
  `jumlah_termin` smallint(5) unsigned DEFAULT NULL,
  `nominal_termin` decimal(16,2) DEFAULT NULL,
  `tanggal_jatuh_tempo_termin` date DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `alasan_batal` varchar(255) DEFAULT NULL,
  `refund_master_bank_id` bigint(20) unsigned DEFAULT NULL,
  `refund_transaksi_keuangan_id` bigint(20) unsigned DEFAULT NULL,
  `refund_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `refund_at` date DEFAULT NULL,
  `refund_status` varchar(255) DEFAULT NULL,
  `refund_requested_by` bigint(20) unsigned DEFAULT NULL,
  `refund_requested_at` timestamp NULL DEFAULT NULL,
  `refund_manager_approved_by` bigint(20) unsigned DEFAULT NULL,
  `refund_manager_approved_at` timestamp NULL DEFAULT NULL,
  `refund_owner_approved_by` bigint(20) unsigned DEFAULT NULL,
  `refund_owner_approved_at` timestamp NULL DEFAULT NULL,
  `refund_rejected_by` bigint(20) unsigned DEFAULT NULL,
  `refund_rejected_at` timestamp NULL DEFAULT NULL,
  `refund_approval_note` text DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `record_status` varchar(255) NOT NULL DEFAULT 'draft',
  `locked_at` timestamp NULL DEFAULT NULL,
  `locked_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `bank_credit_product_id` bigint(20) unsigned DEFAULT NULL,
  `cash_installment_scheme_id` bigint(20) unsigned DEFAULT NULL,
  `developer_kpr_product_id` bigint(20) unsigned DEFAULT NULL,
  `payment_configuration_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payment_configuration_snapshot`)),
  PRIMARY KEY (`id`),
  UNIQUE KEY `sprs_kode_spr_unique` (`kode_spr`),
  KEY `sprs_costumer_id_foreign` (`costumer_id`),
  KEY `sprs_detail_rumah_id_foreign` (`detail_rumah_id`),
  KEY `sprs_created_by_foreign` (`created_by`),
  KEY `sprs_locked_by_foreign` (`locked_by`),
  KEY `sprs_updated_by_foreign` (`updated_by`),
  KEY `sprs_bank_kredit_id_foreign` (`bank_kredit_id`),
  KEY `sprs_refund_master_bank_id_foreign` (`refund_master_bank_id`),
  KEY `sprs_refund_transaksi_keuangan_id_foreign` (`refund_transaksi_keuangan_id`),
  KEY `sprs_refund_requested_by_foreign` (`refund_requested_by`),
  KEY `sprs_refund_manager_approved_by_foreign` (`refund_manager_approved_by`),
  KEY `sprs_refund_owner_approved_by_foreign` (`refund_owner_approved_by`),
  KEY `sprs_refund_rejected_by_foreign` (`refund_rejected_by`),
  KEY `sprs_bank_credit_product_id_foreign` (`bank_credit_product_id`),
  KEY `sprs_cash_installment_scheme_id_foreign` (`cash_installment_scheme_id`),
  KEY `sprs_developer_kpr_product_id_foreign` (`developer_kpr_product_id`),
  KEY `sprs_bank_branch_id_foreign` (`bank_branch_id`),
  KEY `sprs_superseded_by_spr_id_foreign` (`superseded_by_spr_id`),
  CONSTRAINT `sprs_bank_branch_id_foreign` FOREIGN KEY (`bank_branch_id`) REFERENCES `bank_branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sprs_bank_credit_product_id_foreign` FOREIGN KEY (`bank_credit_product_id`) REFERENCES `bank_credit_products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sprs_bank_kredit_id_foreign` FOREIGN KEY (`bank_kredit_id`) REFERENCES `bank_kredits` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sprs_cash_installment_scheme_id_foreign` FOREIGN KEY (`cash_installment_scheme_id`) REFERENCES `cash_installment_schemes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sprs_costumer_id_foreign` FOREIGN KEY (`costumer_id`) REFERENCES `costumers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sprs_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sprs_detail_rumah_id_foreign` FOREIGN KEY (`detail_rumah_id`) REFERENCES `detail_rumahs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sprs_developer_kpr_product_id_foreign` FOREIGN KEY (`developer_kpr_product_id`) REFERENCES `developer_kpr_products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sprs_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sprs_refund_manager_approved_by_foreign` FOREIGN KEY (`refund_manager_approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sprs_refund_master_bank_id_foreign` FOREIGN KEY (`refund_master_bank_id`) REFERENCES `master_banks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sprs_refund_owner_approved_by_foreign` FOREIGN KEY (`refund_owner_approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sprs_refund_rejected_by_foreign` FOREIGN KEY (`refund_rejected_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sprs_refund_requested_by_foreign` FOREIGN KEY (`refund_requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sprs_refund_transaksi_keuangan_id_foreign` FOREIGN KEY (`refund_transaksi_keuangan_id`) REFERENCES `transaksi_keuangans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sprs_superseded_by_spr_id_foreign` FOREIGN KEY (`superseded_by_spr_id`) REFERENCES `sprs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sprs_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `sprs`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
