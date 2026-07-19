<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `customer_documents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `costumer_id` bigint(20) unsigned NOT NULL,
  `dokumen_costumer_id` bigint(20) unsigned DEFAULT NULL,
  `label` varchar(255) DEFAULT NULL,
  `party_scope` varchar(255) NOT NULL DEFAULT 'customer',
  `nama_file` varchar(255) NOT NULL,
  `path_file` varchar(255) NOT NULL,
  `mime_type` varchar(255) DEFAULT NULL,
  `file_size` bigint(20) unsigned NOT NULL DEFAULT 0,
  `document_date` date DEFAULT NULL,
  `expires_at` date DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `version` int(10) unsigned NOT NULL DEFAULT 1,
  `replaces_document_id` bigint(20) unsigned DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `uploaded_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `customer_documents_dokumen_costumer_id_foreign` (`dokumen_costumer_id`),
  KEY `customer_documents_replaces_document_id_foreign` (`replaces_document_id`),
  KEY `customer_documents_uploaded_by_foreign` (`uploaded_by`),
  KEY `customer_document_lookup` (`costumer_id`,`dokumen_costumer_id`,`status`),
  CONSTRAINT `customer_documents_costumer_id_foreign` FOREIGN KEY (`costumer_id`) REFERENCES `costumers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `customer_documents_dokumen_costumer_id_foreign` FOREIGN KEY (`dokumen_costumer_id`) REFERENCES `dokumen_costumers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customer_documents_replaces_document_id_foreign` FOREIGN KEY (`replaces_document_id`) REFERENCES `customer_documents` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customer_documents_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `customer_documents`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
