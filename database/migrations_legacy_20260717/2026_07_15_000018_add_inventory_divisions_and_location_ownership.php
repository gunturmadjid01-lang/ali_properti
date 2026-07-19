<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_divisions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::table('inventory_loans', function (Blueprint $table) {
            $table->foreignId('inventory_division_id')->nullable()->after('division')->constrained()->nullOnDelete();
        });
        Schema::table('inventory_locations', function (Blueprint $table) {
            $table->enum('owner_type', ['company', 'branch', 'housing'])->default('company')->after('type');
            $table->foreignId('branch_id')->nullable()->after('owner_type')->constrained('cabang_perusahaans')->nullOnDelete();
            $table->foreignId('perumahan_id')->nullable()->after('branch_id')->constrained()->nullOnDelete();
        });
        Schema::create('inventory_asset_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_no')->unique();
            $table->date('date');
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('office_asset_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->foreignId('inventory_location_id')->constrained()->restrictOnDelete();
            $table->string('source')->nullable();
            $table->string('reference_no')->nullable();
            $table->text('notes')->nullable();
            $table->string('record_status')->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
        DB::table('inventory_loans')->whereNotNull('division')->where('division', '!=', '')->distinct()->pluck('division')->each(function ($name) {
            $id = DB::table('inventory_divisions')->insertGetId(['name' => $name, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('inventory_loans')->where('division', $name)->update(['inventory_division_id' => $id]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_asset_receipts');
        Schema::table('inventory_locations', fn (Blueprint $table) => $table->dropConstrainedForeignId('perumahan_id'));
        Schema::table('inventory_locations', fn (Blueprint $table) => $table->dropConstrainedForeignId('branch_id'));
        Schema::table('inventory_locations', fn (Blueprint $table) => $table->dropColumn('owner_type'));
        Schema::table('inventory_loans', fn (Blueprint $table) => $table->dropConstrainedForeignId('inventory_division_id'));
        Schema::dropIfExists('inventory_divisions');
    }
};
