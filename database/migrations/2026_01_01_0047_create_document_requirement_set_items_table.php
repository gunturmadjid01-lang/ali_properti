<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `document_requirement_set_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `document_requirement_set_id` bigint(20) unsigned NOT NULL,
  `dokumen_costumer_id` bigint(20) unsigned NOT NULL,
  `employment_categories` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`employment_categories`)),
  `marital_statuses` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`marital_statuses`)),
  `party_scope` varchar(255) NOT NULL DEFAULT 'customer',
  `process_stage_code` varchar(255) DEFAULT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT 1,
  `validity_days` smallint(5) unsigned DEFAULT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `document_set_item_unique` (`document_requirement_set_id`,`dokumen_costumer_id`,`party_scope`),
  KEY `document_requirement_set_items_dokumen_costumer_id_foreign` (`dokumen_costumer_id`),
  KEY `document_requirement_set_items_process_stage_code_index` (`process_stage_code`),
  CONSTRAINT `document_requirement_set_items_dokumen_costumer_id_foreign` FOREIGN KEY (`dokumen_costumer_id`) REFERENCES `dokumen_costumers` (`id`),
  CONSTRAINT `document_set_item_set_fk` FOREIGN KEY (`document_requirement_set_id`) REFERENCES `document_requirement_sets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `document_requirement_set_items`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
