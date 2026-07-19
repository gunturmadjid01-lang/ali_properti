<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `unit_ownerships` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `detail_rumah_id` bigint(20) unsigned NOT NULL,
  `costumer_id` bigint(20) unsigned DEFAULT NULL,
  `spr_id` bigint(20) unsigned DEFAULT NULL,
  `source_type` varchar(255) NOT NULL DEFAULT 'legacy',
  `source_id` bigint(20) unsigned DEFAULT NULL,
  `acquisition_method` varchar(255) NOT NULL DEFAULT 'data_lama',
  `acquired_at` date NOT NULL,
  `ended_at` date DEFAULT NULL,
  `owner_name` varchar(255) NOT NULL,
  `identity_type` varchar(255) DEFAULT NULL,
  `identity_number` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `spouse_name` varchar(255) DEFAULT NULL,
  `document_number` varchar(255) DEFAULT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `record_status` varchar(255) NOT NULL DEFAULT 'draft',
  `locked_at` timestamp NULL DEFAULT NULL,
  `locked_by` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `unit_ownerships_costumer_id_foreign` (`costumer_id`),
  KEY `unit_ownerships_spr_id_foreign` (`spr_id`),
  KEY `unit_ownerships_locked_by_foreign` (`locked_by`),
  KEY `unit_ownerships_created_by_foreign` (`created_by`),
  KEY `unit_ownerships_updated_by_foreign` (`updated_by`),
  KEY `unit_ownerships_detail_rumah_id_is_active_index` (`detail_rumah_id`,`is_active`),
  KEY `unit_ownerships_source_type_source_id_index` (`source_type`,`source_id`),
  CONSTRAINT `unit_ownerships_costumer_id_foreign` FOREIGN KEY (`costumer_id`) REFERENCES `costumers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `unit_ownerships_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `unit_ownerships_detail_rumah_id_foreign` FOREIGN KEY (`detail_rumah_id`) REFERENCES `detail_rumahs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `unit_ownerships_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `unit_ownerships_spr_id_foreign` FOREIGN KEY (`spr_id`) REFERENCES `sprs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `unit_ownerships_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `unit_ownerships`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
