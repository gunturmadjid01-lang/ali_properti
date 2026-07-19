<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `marketing_templates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `kode_template` varchar(255) NOT NULL,
  `nama_template` varchar(255) NOT NULL,
  `kanal` varchar(255) NOT NULL DEFAULT 'whatsapp',
  `tahapan` varchar(255) DEFAULT NULL,
  `isi_template` text NOT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `status` varchar(255) NOT NULL DEFAULT 'aktif',
  `record_status` varchar(255) NOT NULL DEFAULT 'draft',
  `locked_at` timestamp NULL DEFAULT NULL,
  `locked_by` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `marketing_templates_kode_template_unique` (`kode_template`),
  KEY `marketing_templates_locked_by_foreign` (`locked_by`),
  KEY `marketing_templates_created_by_foreign` (`created_by`),
  KEY `marketing_templates_updated_by_foreign` (`updated_by`),
  CONSTRAINT `marketing_templates_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `marketing_templates_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `marketing_templates_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `marketing_templates`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
