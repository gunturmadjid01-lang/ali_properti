<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `sales_process_documents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sales_process_step_id` bigint(20) unsigned NOT NULL,
  `document_type` varchar(255) NOT NULL,
  `document_number` varchar(255) DEFAULT NULL,
  `document_date` date DEFAULT NULL,
  `expires_at` date DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `validation_status` varchar(255) NOT NULL DEFAULT 'uploaded',
  `notes` text DEFAULT NULL,
  `uploaded_by` bigint(20) unsigned DEFAULT NULL,
  `validated_by` bigint(20) unsigned DEFAULT NULL,
  `validated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sales_process_documents_sales_process_step_id_foreign` (`sales_process_step_id`),
  KEY `sales_process_documents_uploaded_by_foreign` (`uploaded_by`),
  KEY `sales_process_documents_validated_by_foreign` (`validated_by`),
  CONSTRAINT `sales_process_documents_sales_process_step_id_foreign` FOREIGN KEY (`sales_process_step_id`) REFERENCES `sales_process_steps` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_process_documents_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_process_documents_validated_by_foreign` FOREIGN KEY (`validated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `sales_process_documents`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
