<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `developer_kpr_product_housing` (
  `developer_kpr_product_id` bigint(20) unsigned NOT NULL,
  `perumahan_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`developer_kpr_product_id`,`perumahan_id`),
  KEY `developer_kpr_product_housing_perumahan_id_foreign` (`perumahan_id`),
  CONSTRAINT `developer_kpr_product_housing_perumahan_id_foreign` FOREIGN KEY (`perumahan_id`) REFERENCES `perumahans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `developer_product_housing_product_fk` FOREIGN KEY (`developer_kpr_product_id`) REFERENCES `developer_kpr_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `developer_kpr_product_housing`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
