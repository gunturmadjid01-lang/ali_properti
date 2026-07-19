<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `cash_sales` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `kode_cash` varchar(255) NOT NULL,
  `spr_id` bigint(20) unsigned NOT NULL,
  `costumer_id` bigint(20) unsigned NOT NULL,
  `detail_rumah_id` bigint(20) unsigned NOT NULL,
  `handled_by` bigint(20) unsigned DEFAULT NULL,
  `tanggal_transaksi` date NOT NULL,
  `harga_rumah` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_tagihan` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_dibayar` decimal(18,2) NOT NULL DEFAULT 0.00,
  `sisa_tagihan` decimal(18,2) NOT NULL DEFAULT 0.00,
  `status_pembayaran` varchar(255) NOT NULL DEFAULT 'menunggu_pembayaran',
  `catatan` text DEFAULT NULL,
  `record_status` varchar(255) NOT NULL DEFAULT 'draft',
  `locked_at` timestamp NULL DEFAULT NULL,
  `locked_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cash_sales_kode_cash_unique` (`kode_cash`),
  KEY `cash_sales_spr_id_foreign` (`spr_id`),
  KEY `cash_sales_costumer_id_foreign` (`costumer_id`),
  KEY `cash_sales_detail_rumah_id_foreign` (`detail_rumah_id`),
  KEY `cash_sales_handled_by_foreign` (`handled_by`),
  KEY `cash_sales_locked_by_foreign` (`locked_by`),
  KEY `cash_sales_created_by_foreign` (`created_by`),
  KEY `cash_sales_updated_by_foreign` (`updated_by`),
  CONSTRAINT `cash_sales_costumer_id_foreign` FOREIGN KEY (`costumer_id`) REFERENCES `costumers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cash_sales_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cash_sales_detail_rumah_id_foreign` FOREIGN KEY (`detail_rumah_id`) REFERENCES `detail_rumahs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cash_sales_handled_by_foreign` FOREIGN KEY (`handled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cash_sales_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cash_sales_spr_id_foreign` FOREIGN KEY (`spr_id`) REFERENCES `sprs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cash_sales_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `cash_sales`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
