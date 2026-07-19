<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `kpr_follow_ups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `kpr_submission_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `tanggal_follow_up` date NOT NULL,
  `metode_follow_up` varchar(255) NOT NULL,
  `status_kpr` varchar(255) NOT NULL,
  `record_status` varchar(255) NOT NULL DEFAULT 'draft',
  `locked_at` timestamp NULL DEFAULT NULL,
  `locked_by` bigint(20) unsigned DEFAULT NULL,
  `hasil_follow_up` text DEFAULT NULL,
  `kendala` text DEFAULT NULL,
  `tindak_lanjut` text DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `rencana_follow_up_at` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `kpr_follow_ups_kpr_submission_id_foreign` (`kpr_submission_id`),
  KEY `kpr_follow_ups_user_id_foreign` (`user_id`),
  KEY `kpr_follow_ups_locked_by_foreign` (`locked_by`),
  KEY `kpr_follow_ups_created_by_foreign` (`created_by`),
  KEY `kpr_follow_ups_updated_by_foreign` (`updated_by`),
  CONSTRAINT `kpr_follow_ups_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `kpr_follow_ups_kpr_submission_id_foreign` FOREIGN KEY (`kpr_submission_id`) REFERENCES `kpr_submissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `kpr_follow_ups_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `kpr_follow_ups_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `kpr_follow_ups_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `kpr_follow_ups`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
