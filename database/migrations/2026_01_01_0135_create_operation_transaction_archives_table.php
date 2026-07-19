<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `operation_transaction_archives` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `module` varchar(30) NOT NULL,
  `section` varchar(50) NOT NULL,
  `record_id` bigint(20) unsigned NOT NULL,
  `document_no` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `responsible_user_id` bigint(20) unsigned DEFAULT NULL,
  `submitted_by` bigint(20) unsigned DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejected_by` bigint(20) unsigned DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `approval_notes` text DEFAULT NULL,
  `last_printed_by` bigint(20) unsigned DEFAULT NULL,
  `last_printed_at` timestamp NULL DEFAULT NULL,
  `print_count` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `operation_archive_record_unique` (`module`,`section`,`record_id`),
  UNIQUE KEY `operation_transaction_archives_document_no_unique` (`document_no`),
  KEY `operation_transaction_archives_responsible_user_id_foreign` (`responsible_user_id`),
  KEY `operation_transaction_archives_submitted_by_foreign` (`submitted_by`),
  KEY `operation_transaction_archives_approved_by_foreign` (`approved_by`),
  KEY `operation_transaction_archives_rejected_by_foreign` (`rejected_by`),
  KEY `operation_transaction_archives_last_printed_by_foreign` (`last_printed_by`),
  CONSTRAINT `operation_transaction_archives_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `operation_transaction_archives_last_printed_by_foreign` FOREIGN KEY (`last_printed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `operation_transaction_archives_rejected_by_foreign` FOREIGN KEY (`rejected_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `operation_transaction_archives_responsible_user_id_foreign` FOREIGN KEY (`responsible_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `operation_transaction_archives_submitted_by_foreign` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `operation_transaction_archives`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
