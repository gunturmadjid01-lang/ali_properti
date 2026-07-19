<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `sales_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `transaction_no` varchar(255) NOT NULL,
  `spr_id` bigint(20) unsigned NOT NULL,
  `costumer_id` bigint(20) unsigned NOT NULL,
  `cabang_perusahaan_id` bigint(20) unsigned DEFAULT NULL,
  `perumahan_id` bigint(20) unsigned NOT NULL,
  `detail_rumah_id` bigint(20) unsigned NOT NULL,
  `marketing_user_id` bigint(20) unsigned DEFAULT NULL,
  `payment_method` varchar(255) NOT NULL,
  `sale_price_snapshot` decimal(18,2) NOT NULL,
  `party_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`party_snapshot`)),
  `payment_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payment_snapshot`)),
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `outcome` varchar(255) DEFAULT NULL,
  `failure_stage` varchar(255) DEFAULT NULL,
  `failure_category` varchar(255) DEFAULT NULL,
  `failure_reason` text DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `approved_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sales_transactions_transaction_no_unique` (`transaction_no`),
  UNIQUE KEY `sales_transactions_spr_id_unique` (`spr_id`),
  KEY `sales_transactions_costumer_id_foreign` (`costumer_id`),
  KEY `sales_transactions_cabang_perusahaan_id_foreign` (`cabang_perusahaan_id`),
  KEY `sales_transactions_perumahan_id_foreign` (`perumahan_id`),
  KEY `sales_transactions_detail_rumah_id_foreign` (`detail_rumah_id`),
  KEY `sales_transactions_marketing_user_id_foreign` (`marketing_user_id`),
  KEY `sales_transactions_approved_by_foreign` (`approved_by`),
  CONSTRAINT `sales_transactions_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_transactions_cabang_perusahaan_id_foreign` FOREIGN KEY (`cabang_perusahaan_id`) REFERENCES `cabang_perusahaans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_transactions_costumer_id_foreign` FOREIGN KEY (`costumer_id`) REFERENCES `costumers` (`id`),
  CONSTRAINT `sales_transactions_detail_rumah_id_foreign` FOREIGN KEY (`detail_rumah_id`) REFERENCES `detail_rumahs` (`id`),
  CONSTRAINT `sales_transactions_marketing_user_id_foreign` FOREIGN KEY (`marketing_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_transactions_perumahan_id_foreign` FOREIGN KEY (`perumahan_id`) REFERENCES `perumahans` (`id`),
  CONSTRAINT `sales_transactions_spr_id_foreign` FOREIGN KEY (`spr_id`) REFERENCES `sprs` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `sales_transactions`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
