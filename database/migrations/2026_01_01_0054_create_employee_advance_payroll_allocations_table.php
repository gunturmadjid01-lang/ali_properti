<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `employee_advance_payroll_allocations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `employee_advance_id` bigint(20) unsigned NOT NULL,
  `payroll_batch_item_id` bigint(20) unsigned NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_advance_payroll_allocations_employee_advance_id_unique` (`employee_advance_id`),
  KEY `advance_payroll_allocation_batch_item_fk` (`payroll_batch_item_id`),
  CONSTRAINT `advance_payroll_allocation_batch_item_fk` FOREIGN KEY (`payroll_batch_item_id`) REFERENCES `payroll_batch_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employee_advance_payroll_allocations_employee_advance_id_foreign` FOREIGN KEY (`employee_advance_id`) REFERENCES `employee_advances` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `employee_advance_payroll_allocations`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
