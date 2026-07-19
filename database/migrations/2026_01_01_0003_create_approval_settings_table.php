<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `approval_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `module_key` varchar(255) NOT NULL,
  `module_label` varchar(255) NOT NULL,
  `action` varchar(255) NOT NULL,
  `requires_approval` tinyint(1) NOT NULL DEFAULT 0,
  `approval_stages` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `approver_role_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`approver_role_ids`)),
  `approval_steps` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`approval_steps`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `approval_settings_module_key_action_unique` (`module_key`,`action`),
  KEY `approval_settings_created_by_foreign` (`created_by`),
  KEY `approval_settings_updated_by_foreign` (`updated_by`),
  CONSTRAINT `approval_settings_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `approval_settings_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `approval_settings`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
