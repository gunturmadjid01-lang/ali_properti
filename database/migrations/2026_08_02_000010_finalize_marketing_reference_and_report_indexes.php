<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('marketing_reference_options')->upsert([
            ['category' => 'follow_up_method', 'code' => 'chat', 'label' => 'Chat', 'sort_order' => 1, 'is_active' => true, 'record_status' => 'locked', 'locked_at' => $now, 'created_at' => $now, 'updated_at' => $now],
            ['category' => 'follow_up_method', 'code' => 'telephone', 'label' => 'Telepon', 'sort_order' => 2, 'is_active' => true, 'record_status' => 'locked', 'locked_at' => $now, 'created_at' => $now, 'updated_at' => $now],
            ['category' => 'follow_up_method', 'code' => 'kunjungan_langsung', 'label' => 'Kunjungan Langsung', 'sort_order' => 3, 'is_active' => true, 'record_status' => 'locked', 'locked_at' => $now, 'created_at' => $now, 'updated_at' => $now],
            ['category' => 'interest_level', 'code' => 'cold', 'label' => 'Dingin', 'sort_order' => 1, 'is_active' => true, 'record_status' => 'locked', 'locked_at' => $now, 'created_at' => $now, 'updated_at' => $now],
            ['category' => 'interest_level', 'code' => 'warm', 'label' => 'Hangat', 'sort_order' => 2, 'is_active' => true, 'record_status' => 'locked', 'locked_at' => $now, 'created_at' => $now, 'updated_at' => $now],
            ['category' => 'interest_level', 'code' => 'hot', 'label' => 'Panas', 'sort_order' => 3, 'is_active' => true, 'record_status' => 'locked', 'locked_at' => $now, 'created_at' => $now, 'updated_at' => $now],
        ], ['category', 'code'], ['label', 'sort_order', 'updated_at']);

        DB::table('costumers')->whereNull('assigned_marketing_id')->whereIn('created_by', function ($query): void {
            $query->select('model_id')->from('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')->where('model_type', 'App\\Models\\User')->whereIn('roles.name', ['marketing', 'area_marketing']);
        })->update(['assigned_marketing_id' => DB::raw('created_by'), 'assigned_at' => DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)')]);

        if (! collect(DB::select("SHOW INDEX FROM marketing_lead_activities WHERE Key_name = 'mkt_activity_user_date_idx'"))->count()) {
            Schema::table('marketing_lead_activities', fn (Blueprint $table) => $table->index(['user_id', 'activity_at'], 'mkt_activity_user_date_idx'));
        }
        $permissions = collect(['marketing-report.view', 'marketing-report.export', 'marketing-audit.view', 'customer.view-all', 'lead.assign', 'lead.transfer', 'follow-up.verify', 'visit-report.verify'])->map(fn ($name) => Permission::findOrCreate($name, 'web'));
        Role::findOrCreate('supervisor_marketing', 'web')->givePermissionTo($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (Schema::hasTable('marketing_lead_activities')) {
            Schema::table('marketing_lead_activities', fn (Blueprint $table) => $table->dropIndex('mkt_activity_user_date_idx'));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
