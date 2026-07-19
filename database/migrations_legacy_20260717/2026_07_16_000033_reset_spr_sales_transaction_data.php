<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();
        try {
            DB::transaction(function (): void {
                $salesSourceTypes = [
                    'App\\Models\\SprPayment', 'App\\Models\\CustomerReceipt',
                    'App\\Models\\BankKprDisbursement', 'App\\Models\\PaymentSchedule',
                    'App\\Models\\CashSalePayment', 'App\\Models\\CashSale',
                ];

                if (Schema::hasTable('journals')) {
                    $journalIds = DB::table('journals')->whereIn('source_type', $salesSourceTypes)->pluck('id');
                    if (Schema::hasTable('journal_details')) {
                        DB::table('journal_details')->whereIn('journal_id', $journalIds)->delete();
                    }
                    DB::table('journals')->whereIn('id', $journalIds)->delete();
                }
                if (Schema::hasTable('transaksi_keuangans')) {
                    DB::table('transaksi_keuangans')->whereIn('source_type', $salesSourceTypes)->delete();
                }

                $tables = [
                    'customer_receipt_allocations', 'bank_kpr_disbursements', 'customer_receipts',
                    'payment_schedules', 'sales_workflow_histories', 'cash_installment_contracts',
                    'developer_kpr_applications', 'bank_kpr_financings', 'cash_sale_payments',
                    'cash_sales', 'kpr_milestone_documents', 'kpr_milestones', 'kpr_stage_histories',
                    'kpr_follow_ups', 'berkas_costumers', 'kpr_submissions', 'spr_billing_schedules',
                    'spr_payments', 'spr_approvals', 'unit_ownerships', 'sales_transactions',
                ];
                foreach ($tables as $table) {
                    if (Schema::hasTable($table)) {
                        DB::table($table)->delete();
                    }
                }

                if (Schema::hasTable('approval_requests')) {
                    DB::table('approval_requests')->whereIn('module_key', [
                        'spr', 'spr-payment', 'cash-sale', 'customer-receipt', 'cash-installment-contract',
                        'developer-kpr-contract', 'kpr-submission', 'kpr-milestone', 'bank-kpr-financing',
                        'bank-kpr-disbursement',
                    ])->delete();
                }
                if (Schema::hasTable('sprs')) {
                    DB::table('sprs')->delete();
                }
                if (Schema::hasTable('detail_rumahs')) {
                    DB::table('detail_rumahs')->update([
                        'status_penjualan' => 'tersedia', 'booking_spr_id' => null, 'booking_at' => null,
                    ]);
                }
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    public function down(): void
    {
        // Reset data transaksi disengaja dan tidak dapat dipulihkan oleh rollback.
    }
};
