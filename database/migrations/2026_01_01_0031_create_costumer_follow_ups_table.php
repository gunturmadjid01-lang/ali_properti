<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `costumer_follow_ups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `costumer_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `tanggal_follow_up` date NOT NULL,
  `metode_follow_up` varchar(255) NOT NULL,
  `status_serius` tinyint(1) NOT NULL DEFAULT 0,
  `progress_kemampuan` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'selesai',
  `catatan` text DEFAULT NULL,
  `rencana_follow_up_at` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `record_status` varchar(255) NOT NULL DEFAULT 'draft',
  `locked_at` timestamp NULL DEFAULT NULL,
  `locked_by` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `costumer_follow_ups_costumer_id_foreign` (`costumer_id`),
  KEY `costumer_follow_ups_user_id_foreign` (`user_id`),
  KEY `costumer_follow_ups_locked_by_foreign` (`locked_by`),
  KEY `costumer_follow_ups_created_by_foreign` (`created_by`),
  KEY `costumer_follow_ups_updated_by_foreign` (`updated_by`),
  CONSTRAINT `costumer_follow_ups_costumer_id_foreign` FOREIGN KEY (`costumer_id`) REFERENCES `costumers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `costumer_follow_ups_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `costumer_follow_ups_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `costumer_follow_ups_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `costumer_follow_ups_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `costumer_follow_ups`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
