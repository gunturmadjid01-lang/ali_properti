<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `marketing_lead_activities` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `costumer_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `status_from` varchar(255) DEFAULT NULL,
  `status_to` varchar(255) NOT NULL,
  `source_type` varchar(255) DEFAULT NULL,
  `source_id` bigint(20) unsigned DEFAULT NULL,
  `activity_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `note` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `marketing_lead_activities_user_id_foreign` (`user_id`),
  KEY `marketing_lead_activities_activity_at_status_to_index` (`activity_at`,`status_to`),
  KEY `marketing_lead_activities_costumer_id_activity_at_index` (`costumer_id`,`activity_at`),
  KEY `marketing_lead_activities_source_type_source_id_index` (`source_type`,`source_id`),
  CONSTRAINT `marketing_lead_activities_costumer_id_foreign` FOREIGN KEY (`costumer_id`) REFERENCES `costumers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `marketing_lead_activities_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `marketing_lead_activities`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
