<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = [
            'approval_requests',
            'approval_settings',
            'app_notifications',
            'bank_kredits',
            'barang_materials',
            'berkas_costumers',
            'cabang_perusahaans',
            'cash_sales',
            'cash_sale_payments',
            'chart_of_accounts',
            'costumer_follow_ups',
            'costumers',
            'detail_perumahan_hpps',
            'detail_rumahs',
            'detail_rumah_hpps',
            'detail_rumah_hpp_items',
            'dokumen_costumers',
            'dokumen_legalitas',
            'dokumen_legalitas_rumahs',
            'foto_perumahans',
            'gudangs',
            'hpp_realisasis',
            'journals',
            'journal_details',
            'kelompok_hpps',
            'kontraktors',
            'kpr_follow_ups',
            'kpr_submissions',
            'master_banks',
            'material_price_histories',
            'material_purchases',
            'material_purchase_details',
            'material_requests',
            'material_request_details',
            'operational_settings',
            'perumahans',
            'perumahan_hpps',
            'promosi_perumahans',
            'progress_pembangunans',
            'realisasi_hpps',
            'spk_kontraktors',
            'spk_kontraktor_additions',
            'spk_kontraktor_payments',
            'sprs',
            'spr_approvals',
            'spr_berkas_costumers',
            'spr_payments',
            'stok_materials',
            'tahapan_pembangunans',
            'tipe_posts',
            'transaksi_keuangans',
            'transaksi_logistiks',
            'transaksi_logistik_details',
            'video_perumahans',
        ];

        foreach ($tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'created_by')) {
                    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                }

                if (! Schema::hasColumn($tableName, 'updated_by')) {
                    $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'approval_requests',
            'approval_settings',
            'app_notifications',
            'bank_kredits',
            'barang_materials',
            'berkas_costumers',
            'cabang_perusahaans',
            'cash_sales',
            'cash_sale_payments',
            'chart_of_accounts',
            'costumer_follow_ups',
            'costumers',
            'detail_perumahan_hpps',
            'detail_rumahs',
            'detail_rumah_hpps',
            'detail_rumah_hpp_items',
            'dokumen_costumers',
            'dokumen_legalitas',
            'dokumen_legalitas_rumahs',
            'foto_perumahans',
            'gudangs',
            'hpp_realisasis',
            'journals',
            'journal_details',
            'kelompok_hpps',
            'kontraktors',
            'kpr_follow_ups',
            'kpr_submissions',
            'master_banks',
            'material_price_histories',
            'material_purchases',
            'material_purchase_details',
            'material_requests',
            'material_request_details',
            'operational_settings',
            'perumahans',
            'perumahan_hpps',
            'promosi_perumahans',
            'progress_pembangunans',
            'realisasi_hpps',
            'spk_kontraktors',
            'spk_kontraktor_additions',
            'spk_kontraktor_payments',
            'sprs',
            'spr_approvals',
            'spr_berkas_costumers',
            'spr_payments',
            'stok_materials',
            'tahapan_pembangunans',
            'tipe_posts',
            'transaksi_keuangans',
            'transaksi_logistiks',
            'transaksi_logistik_details',
            'video_perumahans',
        ];

        foreach ($tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (Schema::hasColumn($tableName, 'updated_by')) {
                    $table->dropConstrainedForeignId('updated_by');
                }

                if (Schema::hasColumn($tableName, 'created_by')) {
                    $table->dropConstrainedForeignId('created_by');
                }
            });
        }
    }
};
