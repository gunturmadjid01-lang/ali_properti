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
        Schema::table('material_opening_balances', function (Blueprint $table) {
            $table->decimal('input_qty', 18, 6)->nullable();
            if (DB::getDriverName() === 'sqlite') {
                $table->unsignedBigInteger('input_unit_id')->nullable();
            } else {
                $table->foreignId('input_unit_id')->nullable()->constrained('material_units')->nullOnDelete();
            }
            $table->string('input_unit_symbol', 50)->nullable();
            $table->decimal('conversion_to_base', 18, 6)->default(1);
        });

        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('material_opening_balances', fn (Blueprint $table) => $table->decimal('qty', 18, 6)->default(0)->change());
        }

        DB::table('material_opening_balances')->orderBy('id')->get()->each(function ($balance) {
            $material = DB::table('barang_materials')->where('id', $balance->barang_material_id)->first();
            $symbol = $material?->base_unit_id ? DB::table('material_units')->where('id', $material->base_unit_id)->value('symbol') : ($material?->satuan ?? null);
            DB::table('material_opening_balances')->where('id', $balance->id)->update([
                'input_qty' => $balance->qty,
                'input_unit_id' => $material?->base_unit_id,
                'input_unit_symbol' => $symbol,
                'conversion_to_base' => 1,
            ]);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permissions = collect(['view', 'create', 'update', 'delete', 'unlock'])->map(fn (string $action) => Permission::findOrCreate("material-opening-balance.{$action}", 'web'));
        Role::query()->whereIn('name', ['user_area_gudang', 'super_admin'])->get()->each(fn (Role $role) => $role->givePermissionTo($permissions));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('material_opening_balances', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropConstrainedForeignId('input_unit_id');
            } else {
                $table->dropColumn('input_unit_id');
            }
            $table->dropColumn(['input_qty', 'input_unit_symbol', 'conversion_to_base']);
        });
    }
};
