<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `petty_cash_expenses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `petty_cash_account_id` bigint(20) unsigned NOT NULL,
  `number` varchar(255) NOT NULL,
  `expense_date` date NOT NULL,
  `category` varchar(255) NOT NULL,
  `cost_type` varchar(255) NOT NULL,
  `perumahan_id` bigint(20) unsigned DEFAULT NULL,
  `detail_rumah_id` bigint(20) unsigned DEFAULT NULL,
  `kelompok_hpp_id` bigint(20) unsigned DEFAULT NULL,
  `tahapan_pembangunan_id` bigint(20) unsigned DEFAULT NULL,
  `amount` decimal(16,2) NOT NULL,
  `description` text NOT NULL,
  `proof_path` varchar(255) NOT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `petty_cash_expenses_number_unique` (`number`),
  KEY `petty_cash_expenses_petty_cash_account_id_foreign` (`petty_cash_account_id`),
  KEY `petty_cash_expenses_detail_rumah_id_foreign` (`detail_rumah_id`),
  KEY `petty_cash_expenses_kelompok_hpp_id_foreign` (`kelompok_hpp_id`),
  KEY `petty_cash_expenses_tahapan_pembangunan_id_foreign` (`tahapan_pembangunan_id`),
  KEY `petty_cash_expenses_created_by_foreign` (`created_by`),
  KEY `petty_cash_expenses_cost_type_expense_date_index` (`cost_type`,`expense_date`),
  KEY `petty_cash_expenses_perumahan_id_detail_rumah_id_index` (`perumahan_id`,`detail_rumah_id`),
  CONSTRAINT `petty_cash_expenses_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `petty_cash_expenses_detail_rumah_id_foreign` FOREIGN KEY (`detail_rumah_id`) REFERENCES `detail_rumahs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `petty_cash_expenses_kelompok_hpp_id_foreign` FOREIGN KEY (`kelompok_hpp_id`) REFERENCES `kelompok_hpps` (`id`) ON DELETE SET NULL,
  CONSTRAINT `petty_cash_expenses_perumahan_id_foreign` FOREIGN KEY (`perumahan_id`) REFERENCES `perumahans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `petty_cash_expenses_petty_cash_account_id_foreign` FOREIGN KEY (`petty_cash_account_id`) REFERENCES `petty_cash_accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `petty_cash_expenses_tahapan_pembangunan_id_foreign` FOREIGN KEY (`tahapan_pembangunan_id`) REFERENCES `tahapan_pembangunans` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `petty_cash_expenses`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
