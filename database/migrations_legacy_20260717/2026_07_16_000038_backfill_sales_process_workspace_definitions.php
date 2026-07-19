<?php

use App\Support\SalesProcessDefinitions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('sales_process_steps')->join('sales_transactions', 'sales_transactions.id', '=', 'sales_process_steps.sales_transaction_id')->select('sales_process_steps.id', 'sales_process_steps.code', 'sales_process_steps.metadata', 'sales_transactions.payment_method')->orderBy('sales_process_steps.id')->get()->each(function ($step) {
            $metadata = json_decode($step->metadata ?: '[]', true) ?: [];
            $data = $metadata['data'] ?? (array_diff_key($metadata, ['dependencies' => true]));
            DB::table('sales_process_steps')->where('id', $step->id)->update(['metadata' => json_encode(['data' => $data, 'dependencies' => SalesProcessDefinitions::dependencies($step->code, $step->payment_method)]), 'updated_at' => now()]);
            foreach (SalesProcessDefinitions::get($step->code)['checklist'] as $item) {
                DB::table('sales_process_checklist_items')->updateOrInsert(['sales_process_step_id' => $step->id, 'item_key' => $item['key']], ['label' => $item['label'], 'is_required' => $item['required'], 'is_completed' => false, 'created_at' => now(), 'updated_at' => now()]);
            }
        });
    }

    public function down(): void {}
};
