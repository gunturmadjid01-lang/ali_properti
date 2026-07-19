<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `spr_berkas_costumers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `spr_id` bigint(20) unsigned NOT NULL,
  `dokumen_costumer_id` bigint(20) unsigned NOT NULL,
  `customer_document_id` bigint(20) unsigned DEFAULT NULL,
  `is_selected` tinyint(1) NOT NULL DEFAULT 1,
  `uploaded_by` bigint(20) unsigned DEFAULT NULL,
  `nama_file` varchar(255) NOT NULL,
  `path_file` varchar(255) NOT NULL,
  `mime_type` varchar(255) DEFAULT NULL,
  `file_size` bigint(20) unsigned NOT NULL DEFAULT 0,
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `spr_berkas_costumers_spr_id_foreign` (`spr_id`),
  KEY `spr_berkas_costumers_dokumen_costumer_id_foreign` (`dokumen_costumer_id`),
  KEY `spr_berkas_costumers_uploaded_by_foreign` (`uploaded_by`),
  KEY `spr_berkas_costumers_created_by_foreign` (`created_by`),
  KEY `spr_berkas_costumers_updated_by_foreign` (`updated_by`),
  KEY `spr_berkas_costumers_customer_document_id_foreign` (`customer_document_id`),
  CONSTRAINT `spr_berkas_costumers_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `spr_berkas_costumers_customer_document_id_foreign` FOREIGN KEY (`customer_document_id`) REFERENCES `customer_documents` (`id`) ON DELETE SET NULL,
  CONSTRAINT `spr_berkas_costumers_dokumen_costumer_id_foreign` FOREIGN KEY (`dokumen_costumer_id`) REFERENCES `dokumen_costumers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `spr_berkas_costumers_spr_id_foreign` FOREIGN KEY (`spr_id`) REFERENCES `sprs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `spr_berkas_costumers_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `spr_berkas_costumers_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `spr_berkas_costumers`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
