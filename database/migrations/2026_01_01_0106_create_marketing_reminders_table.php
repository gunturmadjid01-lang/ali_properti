<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `marketing_reminders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `costumer_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `jenis` varchar(255) NOT NULL DEFAULT 'follow_up',
  `judul` varchar(255) NOT NULL,
  `remind_at` datetime NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'menunggu',
  `catatan` text DEFAULT NULL,
  `source_type` varchar(255) DEFAULT NULL,
  `source_id` bigint(20) unsigned DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `marketing_reminders_costumer_id_foreign` (`costumer_id`),
  KEY `marketing_reminders_user_id_foreign` (`user_id`),
  KEY `marketing_reminders_created_by_foreign` (`created_by`),
  KEY `marketing_reminders_updated_by_foreign` (`updated_by`),
  KEY `marketing_reminders_status_remind_at_index` (`status`,`remind_at`),
  CONSTRAINT `marketing_reminders_costumer_id_foreign` FOREIGN KEY (`costumer_id`) REFERENCES `costumers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `marketing_reminders_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `marketing_reminders_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `marketing_reminders_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `marketing_reminders`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
