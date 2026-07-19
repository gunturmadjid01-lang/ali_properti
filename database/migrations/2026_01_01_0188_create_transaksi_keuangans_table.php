<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `transaksi_keuangans` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cabang_id` bigint(20) unsigned NOT NULL,
  `perumahan_id` bigint(20) unsigned DEFAULT NULL,
  `master_bank_id` bigint(20) unsigned DEFAULT NULL,
  `tipe_post_id` bigint(20) unsigned NOT NULL,
  `journal_id` bigint(20) unsigned DEFAULT NULL,
  `tanggal` date NOT NULL,
  `nominal` float NOT NULL,
  `keterangan` text NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `source_type` varchar(255) DEFAULT NULL,
  `source_id` bigint(20) unsigned DEFAULT NULL,
  `nomor_referensi` varchar(255) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'posted',
  PRIMARY KEY (`id`),
  KEY `transaksi_keuangans_cabang_id_foreign` (`cabang_id`),
  KEY `transaksi_keuangans_tipe_post_id_foreign` (`tipe_post_id`),
  KEY `transaksi_keuangans_user_id_foreign` (`user_id`),
  KEY `transaksi_keuangans_master_bank_id_foreign` (`master_bank_id`),
  KEY `transaksi_keuangans_created_by_foreign` (`created_by`),
  KEY `transaksi_keuangans_updated_by_foreign` (`updated_by`),
  KEY `transaksi_keuangans_perumahan_id_foreign` (`perumahan_id`),
  KEY `transaksi_keuangans_journal_id_foreign` (`journal_id`),
  KEY `transaksi_keuangans_source_type_source_id_index` (`source_type`,`source_id`),
  CONSTRAINT `transaksi_keuangans_cabang_id_foreign` FOREIGN KEY (`cabang_id`) REFERENCES `cabang_perusahaans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transaksi_keuangans_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `transaksi_keuangans_journal_id_foreign` FOREIGN KEY (`journal_id`) REFERENCES `journals` (`id`) ON DELETE SET NULL,
  CONSTRAINT `transaksi_keuangans_master_bank_id_foreign` FOREIGN KEY (`master_bank_id`) REFERENCES `master_banks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `transaksi_keuangans_perumahan_id_foreign` FOREIGN KEY (`perumahan_id`) REFERENCES `perumahans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `transaksi_keuangans_tipe_post_id_foreign` FOREIGN KEY (`tipe_post_id`) REFERENCES `tipe_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transaksi_keuangans_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `transaksi_keuangans_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `transaksi_keuangans`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
