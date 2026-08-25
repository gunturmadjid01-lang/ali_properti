<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_request_details', function (Blueprint $table): void {
            $table->decimal('input_qty', 18, 6)->nullable()->after('qty');
            $table->foreignId('input_unit_id')->nullable()->after('input_qty')->constrained('material_units')->nullOnDelete();
            $table->decimal('conversion_to_base', 18, 6)->default(1)->after('input_unit_id');
        });

        Schema::table('material_usages', function (Blueprint $table): void {
            $table->foreignId('material_request_id')->nullable()->after('progress_pembangunan_id')->constrained('material_requests')->nullOnDelete();
            $table->timestamp('stock_posted_at')->nullable()->after('material_request_id');
        });

        Schema::table('material_usage_details', function (Blueprint $table): void {
            $table->decimal('input_qty', 18, 6)->nullable()->after('qty');
            $table->foreignId('input_unit_id')->nullable()->after('input_qty')->constrained('material_units')->nullOnDelete();
            $table->decimal('conversion_to_base', 18, 6)->default(1)->after('input_unit_id');
        });

        DB::table('material_usages')->whereExists(function ($query): void {
            $query->selectRaw('1')->from('material_usage_details')->whereColumn('material_usage_details.material_usage_id', 'material_usages.id');
        })->update(['stock_posted_at' => DB::raw('COALESCE(created_at, NOW())')]);
    }

    public function down(): void
    {
        Schema::table('material_usage_details', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('input_unit_id');
            $table->dropColumn(['input_qty', 'conversion_to_base']);
        });
        Schema::table('material_usages', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('material_request_id');
            $table->dropColumn('stock_posted_at');
        });
        Schema::table('material_request_details', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('input_unit_id');
            $table->dropColumn(['input_qty', 'conversion_to_base']);
        });
    }
};
