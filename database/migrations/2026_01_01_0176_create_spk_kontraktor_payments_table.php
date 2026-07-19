<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `spk_kontraktor_payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `spk_kontraktor_id` bigint(20) unsigned NOT NULL,
  `contractor_opname_id` bigint(20) unsigned DEFAULT NULL,
  `termin_ke` smallint(5) unsigned NOT NULL DEFAULT 1,
  `tanggal_jatuh_tempo` date DEFAULT NULL,
  `tanggal_pembayaran` date DEFAULT NULL,
  `nominal` decimal(16,2) NOT NULL DEFAULT 0.00,
  `keterangan` text DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'menunggu_pengajuan',
  `requested_by` bigint(20) unsigned DEFAULT NULL,
  `requested_at` timestamp NULL DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `released_by` bigint(20) unsigned DEFAULT NULL,
  `released_at` timestamp NULL DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `spk_kontraktor_payments_spk_kontraktor_id_foreign` (`spk_kontraktor_id`),
  KEY `spk_kontraktor_payments_requested_by_foreign` (`requested_by`),
  KEY `spk_kontraktor_payments_approved_by_foreign` (`approved_by`),
  KEY `spk_kontraktor_payments_released_by_foreign` (`released_by`),
  KEY `spk_kontraktor_payments_created_by_foreign` (`created_by`),
  KEY `spk_kontraktor_payments_updated_by_foreign` (`updated_by`),
  KEY `spk_kontraktor_payments_contractor_opname_id_foreign` (`contractor_opname_id`),
  CONSTRAINT `spk_kontraktor_payments_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `spk_kontraktor_payments_contractor_opname_id_foreign` FOREIGN KEY (`contractor_opname_id`) REFERENCES `contractor_opnames` (`id`) ON DELETE SET NULL,
  CONSTRAINT `spk_kontraktor_payments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `spk_kontraktor_payments_released_by_foreign` FOREIGN KEY (`released_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `spk_kontraktor_payments_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `spk_kontraktor_payments_spk_kontraktor_id_foreign` FOREIGN KEY (`spk_kontraktor_id`) REFERENCES `spk_kontraktors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `spk_kontraktor_payments_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `spk_kontraktor_payments`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
