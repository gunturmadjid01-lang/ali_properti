<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `sales_process_checklist_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sales_process_step_id` bigint(20) unsigned NOT NULL,
  `item_key` varchar(255) NOT NULL,
  `label` varchar(255) NOT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT 1,
  `is_completed` tinyint(1) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `completed_by` bigint(20) unsigned DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sales_step_check_key_unique` (`sales_process_step_id`,`item_key`),
  KEY `sales_process_checklist_items_completed_by_foreign` (`completed_by`),
  CONSTRAINT `sales_process_checklist_items_completed_by_foreign` FOREIGN KEY (`completed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_process_checklist_items_sales_process_step_id_foreign` FOREIGN KEY (`sales_process_step_id`) REFERENCES `sales_process_steps` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `sales_process_checklist_items`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
