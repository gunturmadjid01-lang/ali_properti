<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `document_requirement_set_partnerships` (
  `document_requirement_set_id` bigint(20) unsigned NOT NULL,
  `bank_housing_partnership_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`document_requirement_set_id`,`bank_housing_partnership_id`),
  KEY `doc_set_partnerships_target_fk` (`bank_housing_partnership_id`),
  CONSTRAINT `doc_set_partnerships_set_fk` FOREIGN KEY (`document_requirement_set_id`) REFERENCES `document_requirement_sets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `doc_set_partnerships_target_fk` FOREIGN KEY (`bank_housing_partnership_id`) REFERENCES `bank_housing_partnerships` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `document_requirement_set_partnerships`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
