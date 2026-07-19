<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `sales_process_customer_documents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sales_process_step_id` bigint(20) unsigned NOT NULL,
  `customer_document_id` bigint(20) unsigned NOT NULL,
  `document_requirement_set_item_id` bigint(20) unsigned DEFAULT NULL,
  `validation_status` varchar(255) NOT NULL DEFAULT 'selected',
  `validation_notes` text DEFAULT NULL,
  `selected_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `stage_customer_document_unique` (`sales_process_step_id`,`customer_document_id`),
  KEY `sales_process_customer_documents_customer_document_id_foreign` (`customer_document_id`),
  KEY `stage_customer_document_requirement_fk` (`document_requirement_set_item_id`),
  KEY `sales_process_customer_documents_selected_by_foreign` (`selected_by`),
  CONSTRAINT `sales_process_customer_documents_customer_document_id_foreign` FOREIGN KEY (`customer_document_id`) REFERENCES `customer_documents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_process_customer_documents_sales_process_step_id_foreign` FOREIGN KEY (`sales_process_step_id`) REFERENCES `sales_process_steps` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_process_customer_documents_selected_by_foreign` FOREIGN KEY (`selected_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `stage_customer_document_requirement_fk` FOREIGN KEY (`document_requirement_set_item_id`) REFERENCES `document_requirement_set_items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `sales_process_customer_documents`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
