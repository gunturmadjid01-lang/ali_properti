<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `document_requirement_set_companies` (
  `document_requirement_set_id` bigint(20) unsigned NOT NULL,
  `cabang_perusahaan_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`document_requirement_set_id`,`cabang_perusahaan_id`),
  KEY `doc_set_companies_target_fk` (`cabang_perusahaan_id`),
  CONSTRAINT `doc_set_companies_set_fk` FOREIGN KEY (`document_requirement_set_id`) REFERENCES `document_requirement_sets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `doc_set_companies_target_fk` FOREIGN KEY (`cabang_perusahaan_id`) REFERENCES `cabang_perusahaans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `document_requirement_set_companies`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
