<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('chart_of_accounts')->updateOrInsert(
            ['kode_akun' => '1-1300'],
            ['nama_akun' => 'Persediaan Material', 'kategori' => 'aset', 'posisi_normal' => 'debit', 'status' => 'aktif', 'is_system' => true, 'created_at' => now(), 'updated_at' => now()],
        );
        DB::table('chart_of_accounts')->updateOrInsert(
            ['kode_akun' => '1-1500'],
            ['nama_akun' => 'Piutang Penambahan Mutu', 'kategori' => 'aset', 'posisi_normal' => 'debit', 'status' => 'aktif', 'is_system' => true, 'created_at' => now(), 'updated_at' => now()],
        );

        $inventoryId = DB::table('chart_of_accounts')->where('kode_akun', '1-1300')->value('id');
        $upgradeReceivableId = DB::table('chart_of_accounts')->where('kode_akun', '1-1500')->value('id');
        DB::table('journal_details')
            ->where('chart_of_account_id', $inventoryId)
            ->whereExists(fn ($query) => $query->selectRaw('1')->from('journals')
                ->whereColumn('journals.id', 'journal_details.journal_id')
                ->whereIn('journals.type', ['quality_upgrade_invoice', 'quality_upgrade_addendum_invoice']))
            ->update(['chart_of_account_id' => $upgradeReceivableId]);

        Schema::table('material_requests', function (Blueprint $table): void {
            $table->foreignId('quality_upgrade_contract_id')->nullable()->after('progress_pembangunan_id')->constrained()->nullOnDelete();
            $table->foreignId('quality_upgrade_contract_item_id')->nullable()->after('quality_upgrade_contract_id')->constrained()->nullOnDelete();
            $table->index(['quality_upgrade_contract_id', 'status'], 'material_request_upgrade_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('material_requests', function (Blueprint $table): void {
            $table->dropIndex('material_request_upgrade_status_idx');
            $table->dropConstrainedForeignId('quality_upgrade_contract_item_id');
            $table->dropConstrainedForeignId('quality_upgrade_contract_id');
        });
    }
};
