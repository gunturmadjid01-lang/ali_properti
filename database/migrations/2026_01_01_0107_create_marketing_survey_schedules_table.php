<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `marketing_survey_schedules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `kode_survey` varchar(255) NOT NULL,
  `costumer_id` bigint(20) unsigned NOT NULL,
  `perumahan_id` bigint(20) unsigned DEFAULT NULL,
  `detail_rumah_id` bigint(20) unsigned DEFAULT NULL,
  `marketing_id` bigint(20) unsigned DEFAULT NULL,
  `tanggal_survey` datetime NOT NULL,
  `metode_survey` varchar(255) NOT NULL DEFAULT 'kunjungan_lokasi',
  `status` varchar(255) NOT NULL DEFAULT 'dijadwalkan',
  `hasil_survey` text DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `rencana_follow_up_at` datetime DEFAULT NULL,
  `record_status` varchar(255) NOT NULL DEFAULT 'draft',
  `locked_at` timestamp NULL DEFAULT NULL,
  `locked_by` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `marketing_survey_schedules_kode_survey_unique` (`kode_survey`),
  KEY `marketing_survey_schedules_costumer_id_foreign` (`costumer_id`),
  KEY `marketing_survey_schedules_perumahan_id_foreign` (`perumahan_id`),
  KEY `marketing_survey_schedules_detail_rumah_id_foreign` (`detail_rumah_id`),
  KEY `marketing_survey_schedules_marketing_id_foreign` (`marketing_id`),
  KEY `marketing_survey_schedules_locked_by_foreign` (`locked_by`),
  KEY `marketing_survey_schedules_created_by_foreign` (`created_by`),
  KEY `marketing_survey_schedules_updated_by_foreign` (`updated_by`),
  CONSTRAINT `marketing_survey_schedules_costumer_id_foreign` FOREIGN KEY (`costumer_id`) REFERENCES `costumers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `marketing_survey_schedules_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `marketing_survey_schedules_detail_rumah_id_foreign` FOREIGN KEY (`detail_rumah_id`) REFERENCES `detail_rumahs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `marketing_survey_schedules_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `marketing_survey_schedules_marketing_id_foreign` FOREIGN KEY (`marketing_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `marketing_survey_schedules_perumahan_id_foreign` FOREIGN KEY (`perumahan_id`) REFERENCES `perumahans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `marketing_survey_schedules_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `marketing_survey_schedules`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
