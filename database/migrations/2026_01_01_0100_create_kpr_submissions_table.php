<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `kpr_submissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `kode_kpr` varchar(255) NOT NULL,
  `spr_id` bigint(20) unsigned NOT NULL,
  `bank_kredit_id` bigint(20) unsigned DEFAULT NULL,
  `handled_by` bigint(20) unsigned DEFAULT NULL,
  `tanggal_pengajuan` date DEFAULT NULL,
  `nilai_pengajuan` decimal(18,2) NOT NULL DEFAULT 0.00,
  `status` varchar(255) NOT NULL DEFAULT 'pengumpulan_dokumen',
  `catatan` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `record_status` varchar(255) NOT NULL DEFAULT 'draft',
  `locked_at` timestamp NULL DEFAULT NULL,
  `locked_by` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `bank_branch_id` bigint(20) unsigned DEFAULT NULL,
  `bank_credit_product_id` bigint(20) unsigned DEFAULT NULL,
  `bank_credit_product_version_id` bigint(20) unsigned DEFAULT NULL,
  `bank_product_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`bank_product_snapshot`)),
  PRIMARY KEY (`id`),
  UNIQUE KEY `kpr_submissions_kode_kpr_unique` (`kode_kpr`),
  KEY `kpr_submissions_spr_id_foreign` (`spr_id`),
  KEY `kpr_submissions_bank_kredit_id_foreign` (`bank_kredit_id`),
  KEY `kpr_submissions_handled_by_foreign` (`handled_by`),
  KEY `kpr_submissions_locked_by_foreign` (`locked_by`),
  KEY `kpr_submissions_created_by_foreign` (`created_by`),
  KEY `kpr_submissions_updated_by_foreign` (`updated_by`),
  KEY `kpr_submissions_bank_branch_id_foreign` (`bank_branch_id`),
  KEY `kpr_submissions_bank_credit_product_id_foreign` (`bank_credit_product_id`),
  KEY `kpr_submissions_bank_credit_product_version_id_foreign` (`bank_credit_product_version_id`),
  CONSTRAINT `kpr_submissions_bank_branch_id_foreign` FOREIGN KEY (`bank_branch_id`) REFERENCES `bank_branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `kpr_submissions_bank_credit_product_id_foreign` FOREIGN KEY (`bank_credit_product_id`) REFERENCES `bank_credit_products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `kpr_submissions_bank_credit_product_version_id_foreign` FOREIGN KEY (`bank_credit_product_version_id`) REFERENCES `bank_credit_product_versions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `kpr_submissions_bank_kredit_id_foreign` FOREIGN KEY (`bank_kredit_id`) REFERENCES `bank_kredits` (`id`) ON DELETE SET NULL,
  CONSTRAINT `kpr_submissions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `kpr_submissions_handled_by_foreign` FOREIGN KEY (`handled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `kpr_submissions_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `kpr_submissions_spr_id_foreign` FOREIGN KEY (`spr_id`) REFERENCES `sprs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `kpr_submissions_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `kpr_submissions`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
