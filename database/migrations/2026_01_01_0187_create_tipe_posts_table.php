<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `tipe_posts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nama_post` varchar(255) NOT NULL,
  `jenis` varchar(255) NOT NULL,
  `debit_account_id` bigint(20) unsigned DEFAULT NULL,
  `credit_account_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(255) NOT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `record_status` varchar(255) NOT NULL DEFAULT 'draft',
  `locked_at` timestamp NULL DEFAULT NULL,
  `locked_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tipe_posts_locked_by_foreign` (`locked_by`),
  KEY `tipe_posts_debit_account_id_foreign` (`debit_account_id`),
  KEY `tipe_posts_credit_account_id_foreign` (`credit_account_id`),
  KEY `tipe_posts_created_by_foreign` (`created_by`),
  KEY `tipe_posts_updated_by_foreign` (`updated_by`),
  CONSTRAINT `tipe_posts_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tipe_posts_credit_account_id_foreign` FOREIGN KEY (`credit_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tipe_posts_debit_account_id_foreign` FOREIGN KEY (`debit_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tipe_posts_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tipe_posts_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `tipe_posts`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
