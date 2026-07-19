<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `sales_stage_document_checklists` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sales_process_step_id` bigint(20) unsigned NOT NULL,
  `document_requirement_set_item_id` bigint(20) unsigned NOT NULL,
  `is_complete` tinyint(1) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `checked_by` bigint(20) unsigned DEFAULT NULL,
  `checked_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `stage_requirement_check_unique` (`sales_process_step_id`,`document_requirement_set_item_id`),
  KEY `stage_document_check_requirement_fk` (`document_requirement_set_item_id`),
  KEY `sales_stage_document_checklists_checked_by_foreign` (`checked_by`),
  CONSTRAINT `sales_stage_document_checklists_checked_by_foreign` FOREIGN KEY (`checked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_stage_document_checklists_sales_process_step_id_foreign` FOREIGN KEY (`sales_process_step_id`) REFERENCES `sales_process_steps` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stage_document_check_requirement_fk` FOREIGN KEY (`document_requirement_set_item_id`) REFERENCES `document_requirement_set_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `sales_stage_document_checklists`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
