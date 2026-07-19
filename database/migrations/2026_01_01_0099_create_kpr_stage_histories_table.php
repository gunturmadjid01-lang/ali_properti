<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `kpr_stage_histories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `kpr_submission_id` bigint(20) unsigned NOT NULL,
  `tahapan` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL,
  `tanggal_status` datetime NOT NULL,
  `catatan` text DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `kpr_stage_histories_user_id_foreign` (`user_id`),
  KEY `kpr_stage_histories_kpr_submission_id_tanggal_status_index` (`kpr_submission_id`,`tanggal_status`),
  CONSTRAINT `kpr_stage_histories_kpr_submission_id_foreign` FOREIGN KEY (`kpr_submission_id`) REFERENCES `kpr_submissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `kpr_stage_histories_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `kpr_stage_histories`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
