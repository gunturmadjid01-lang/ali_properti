<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared(<<<'SQL'
CREATE TABLE `customer_receipt_allocations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `customer_receipt_id` bigint(20) unsigned NOT NULL,
  `payment_schedule_id` bigint(20) unsigned DEFAULT NULL,
  `amount` decimal(18,2) NOT NULL,
  `allocation_type` varchar(255) NOT NULL DEFAULT 'invoice',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `customer_receipt_allocations_customer_receipt_id_foreign` (`customer_receipt_id`),
  KEY `customer_receipt_allocations_payment_schedule_id_foreign` (`payment_schedule_id`),
  CONSTRAINT `customer_receipt_allocations_customer_receipt_id_foreign` FOREIGN KEY (`customer_receipt_id`) REFERENCES `customer_receipts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `customer_receipt_allocations_payment_schedule_id_foreign` FOREIGN KEY (`payment_schedule_id`) REFERENCES `payment_schedules` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS `customer_receipt_allocations`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
