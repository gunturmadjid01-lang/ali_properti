<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('petty_cash_fundings', function (Blueprint $table) {
            $table->string('record_status')->default('draft')->after('status');
            $table->timestamp('locked_at')->nullable()->after('submitted_at');
            $table->foreignId('locked_by')->nullable()->after('locked_at')->constrained('users')->nullOnDelete();
        });

        DB::table('petty_cash_fundings')->whereIn('status', ['pending', 'approved'])->update([
            'record_status' => 'locked',
            'locked_at' => DB::raw('COALESCE(submitted_at, created_at)'),
            'locked_by' => DB::raw('requested_by'),
        ]);

        DB::table('approval_settings')->insertOrIgnore([
            'module_key' => 'petty-cash-funding', 'module_label' => 'Pengisian Kas Kecil', 'action' => 'lock',
            'requires_approval' => false, 'approval_stages' => 0, 'approval_steps' => json_encode([]),
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $permission = Permission::findOrCreate('petty-cash.disburse', 'web');
        Role::query()->whereIn('name', ['keuangan', 'admin_keuangan'])->get()->each(fn (Role $role) => $role->givePermissionTo($permission));
    }

    public function down(): void
    {
        Permission::query()->where('name', 'petty-cash.disburse')->delete();
        DB::table('approval_settings')->where(['module_key' => 'petty-cash-funding', 'action' => 'lock'])->delete();
        Schema::table('petty_cash_fundings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('locked_by');
            $table->dropColumn(['record_status', 'locked_at']);
        });
    }
};
