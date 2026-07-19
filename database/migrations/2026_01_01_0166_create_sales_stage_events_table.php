<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `sales_stage_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sales_method_attempt_id` bigint(20) unsigned NOT NULL,
  `sales_process_step_id` bigint(20) unsigned DEFAULT NULL,
  `stage_code` varchar(255) NOT NULL,
  `event_type` varchar(255) NOT NULL,
  `from_status` varchar(255) DEFAULT NULL,
  `to_status` varchar(255) DEFAULT NULL,
  `outcome` varchar(255) DEFAULT NULL,
  `reason_category` varchar(255) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `occurred_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sales_stage_events_sales_method_attempt_id_foreign` (`sales_method_attempt_id`),
  KEY `sales_stage_events_sales_process_step_id_foreign` (`sales_process_step_id`),
  KEY `sales_stage_events_user_id_foreign` (`user_id`),
  KEY `sales_stage_events_stage_code_event_type_occurred_at_index` (`stage_code`,`event_type`,`occurred_at`),
  CONSTRAINT `sales_stage_events_sales_method_attempt_id_foreign` FOREIGN KEY (`sales_method_attempt_id`) REFERENCES `sales_method_attempts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_stage_events_sales_process_step_id_foreign` FOREIGN KEY (`sales_process_step_id`) REFERENCES `sales_process_steps` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_stage_events_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `sales_stage_events`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
