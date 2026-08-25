<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaksi_keuangans', function (Blueprint $table): void {
            $table->string('record_status')->default('locked')->after('status')->index();
            $table->timestamp('locked_at')->nullable()->after('record_status');
            $table->foreignId('locked_by')->nullable()->after('locked_at')->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable()->after('locked_by');
            $table->foreignId('posted_by')->nullable()->after('posted_at')->constrained('users')->nullOnDelete();
        });

        $financeRoleId = DB::table('roles')->whereIn('name', ['keuangan', 'admin_keuangan'])->orderByRaw("FIELD(name, 'keuangan', 'admin_keuangan')")->value('id');

        DB::table('approval_settings')->insertOrIgnore([
            'module_key' => 'financial-transaction',
            'module_label' => 'Transaksi Kas & Bank Manual',
            'action' => 'lock',
            'requires_approval' => (bool) $financeRoleId,
            'approval_stages' => $financeRoleId ? 1 : 0,
            'approver_role_ids' => json_encode($financeRoleId ? [$financeRoleId] : []),
            'approval_steps' => json_encode($financeRoleId ? [['step' => 1, 'role_ids' => [$financeRoleId]]] : []),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('approval_settings')->where([
            'module_key' => 'financial-transaction',
            'action' => 'lock',
        ])->delete();

        Schema::table('transaksi_keuangans', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('posted_by');
            $table->dropConstrainedForeignId('locked_by');
            $table->dropColumn(['record_status', 'locked_at', 'posted_at']);
        });
    }
};
