<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $legacyLeadIds = DB::table('marketing_leads')->where('lead_no', 'like', 'LEGACY-%')->pluck('id');
            $legacyCustomerIds = DB::table('costumers')->where('customer_stage', 'legacy')->pluck('id');
            $workItemIds = DB::table('sales_work_items')->whereIn('costumer_id', $legacyCustomerIds)->pluck('id');

            DB::table('sales_activity_logs')->where(function ($query) use ($legacyCustomerIds, $workItemIds): void {
                $query->where(fn ($query) => $query->where('subject_type', 'App\\Models\\Costumer')->whereIn('subject_id', $legacyCustomerIds))
                    ->orWhere(fn ($query) => $query->where('subject_type', 'App\\Models\\SalesWorkItem')->whereIn('subject_id', $workItemIds));
            })->delete();
            DB::table('sales_work_items')->whereIn('id', $workItemIds)->delete();
            DB::table('costumers')->whereIn('id', $legacyCustomerIds)->delete();
            DB::table('marketing_leads')->whereIn('id', $legacyLeadIds)->delete();
        });
    }

    public function down(): void
    {
        // Data legacy sengaja tidak dipulihkan. Snapshot database sebelum refactor tetap menjadi jalur pemulihan manual.
    }
};
