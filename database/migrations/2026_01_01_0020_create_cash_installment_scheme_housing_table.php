<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `cash_installment_scheme_housing` (
  `cash_installment_scheme_id` bigint(20) unsigned NOT NULL,
  `perumahan_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`cash_installment_scheme_id`,`perumahan_id`),
  KEY `cash_installment_scheme_housing_perumahan_id_foreign` (`perumahan_id`),
  CONSTRAINT `cash_installment_scheme_housing_perumahan_id_foreign` FOREIGN KEY (`perumahan_id`) REFERENCES `perumahans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cash_scheme_housing_scheme_fk` FOREIGN KEY (`cash_installment_scheme_id`) REFERENCES `cash_installment_schemes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `cash_installment_scheme_housing`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
