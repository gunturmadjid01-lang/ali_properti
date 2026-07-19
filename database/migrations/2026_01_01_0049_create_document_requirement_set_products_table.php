<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `document_requirement_set_products` (
  `document_requirement_set_id` bigint(20) unsigned NOT NULL,
  `bank_credit_product_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`document_requirement_set_id`,`bank_credit_product_id`),
  KEY `doc_set_products_target_fk` (`bank_credit_product_id`),
  CONSTRAINT `doc_set_products_set_fk` FOREIGN KEY (`document_requirement_set_id`) REFERENCES `document_requirement_sets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `doc_set_products_target_fk` FOREIGN KEY (`bank_credit_product_id`) REFERENCES `bank_credit_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `document_requirement_set_products`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
