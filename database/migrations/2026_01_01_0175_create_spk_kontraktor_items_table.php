<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `spk_kontraktor_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `spk_kontraktor_id` bigint(20) unsigned NOT NULL,
  `tahapan_pembangunan_id` bigint(20) unsigned DEFAULT NULL,
  `nama_tahap_pekerjaan` varchar(255) NOT NULL,
  `nama_pekerjaan` varchar(255) NOT NULL,
  `volume` decimal(16,2) NOT NULL DEFAULT 0.00,
  `satuan` varchar(255) NOT NULL DEFAULT '',
  `harga_satuan` decimal(16,2) NOT NULL DEFAULT 0.00,
  `total` decimal(16,2) NOT NULL DEFAULT 0.00,
  `urutan` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `spk_kontraktor_items_spk_kontraktor_id_foreign` (`spk_kontraktor_id`),
  KEY `spk_kontraktor_items_tahapan_pembangunan_id_foreign` (`tahapan_pembangunan_id`),
  CONSTRAINT `spk_kontraktor_items_spk_kontraktor_id_foreign` FOREIGN KEY (`spk_kontraktor_id`) REFERENCES `spk_kontraktors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `spk_kontraktor_items_tahapan_pembangunan_id_foreign` FOREIGN KEY (`tahapan_pembangunan_id`) REFERENCES `tahapan_pembangunans` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `spk_kontraktor_items`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
