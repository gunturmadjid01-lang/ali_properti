<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spk_kontraktors', function (Blueprint $table) {
            if (! Schema::hasColumn('spk_kontraktors', 'record_status')) {
                $table->string('record_status')->default('draft')->after('status');
            }
            if (! Schema::hasColumn('spk_kontraktors', 'locked_at')) {
                $table->timestamp('locked_at')->nullable()->after('record_status');
            }
            if (! Schema::hasColumn('spk_kontraktors', 'locked_by')) {
                $table->foreignId('locked_by')->nullable()->after('locked_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('spk_kontraktors', 'approval_role')) {
                $table->string('approval_role')->default('manager')->after('metode_pembayaran');
            }
        });

        Schema::table('progress_pembangunans', function (Blueprint $table) {
            if (! Schema::hasColumn('progress_pembangunans', 'approval_status')) {
                $table->string('approval_status')->default('menunggu_approval_manager')->after('foto');
            }
            if (! Schema::hasColumn('progress_pembangunans', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('approval_status')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('progress_pembangunans', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
            if (! Schema::hasColumn('progress_pembangunans', 'approved_note')) {
                $table->text('approved_note')->nullable()->after('approved_at');
            }
        });

        Schema::table('material_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('material_requests', 'approved_by_gudang')) {
                $table->foreignId('approved_by_gudang')->nullable()->after('processed_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('material_requests', 'approved_at_gudang')) {
                $table->timestamp('approved_at_gudang')->nullable()->after('approved_by_gudang');
            }
            if (! Schema::hasColumn('material_requests', 'approval_note')) {
                $table->text('approval_note')->nullable()->after('approved_at_gudang');
            }
        });
    }

    public function down(): void
    {
        Schema::table('material_requests', function (Blueprint $table) {
            if (Schema::hasColumn('material_requests', 'approval_note')) {
                $table->dropColumn('approval_note');
            }
            if (Schema::hasColumn('material_requests', 'approved_at_gudang')) {
                $table->dropConstrainedForeignId('approved_by_gudang');
                $table->dropColumn('approved_at_gudang');
            }
        });

        Schema::table('progress_pembangunans', function (Blueprint $table) {
            foreach (['approved_note', 'approved_at'] as $column) {
                if (Schema::hasColumn('progress_pembangunans', $column)) {
                    $table->dropColumn($column);
                }
            }
            if (Schema::hasColumn('progress_pembangunans', 'approved_by')) {
                $table->dropConstrainedForeignId('approved_by');
            }
            if (Schema::hasColumn('progress_pembangunans', 'approval_status')) {
                $table->dropColumn('approval_status');
            }
        });

        Schema::table('spk_kontraktors', function (Blueprint $table) {
            if (Schema::hasColumn('spk_kontraktors', 'approval_role')) {
                $table->dropColumn('approval_role');
            }
            if (Schema::hasColumn('spk_kontraktors', 'locked_by')) {
                $table->dropConstrainedForeignId('locked_by');
            }
            if (Schema::hasColumn('spk_kontraktors', 'locked_at')) {
                $table->dropColumn('locked_at');
            }
            if (Schema::hasColumn('spk_kontraktors', 'record_status')) {
                $table->dropColumn('record_status');
            }
        });
    }
};
