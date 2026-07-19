<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_groups', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->decimal('base_quantity', 18, 6)->default(1);
            $table->string('base_unit', 50)->default('item');
            $table->text('notes')->nullable();
            $table->string('status')->default('aktif');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('material_group_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_group_id')->constrained('material_groups')->cascadeOnDelete();
            $table->foreignId('barang_material_id')->constrained('barang_materials')->restrictOnDelete();
            $table->foreignId('material_unit_id')->constrained('material_units')->restrictOnDelete();
            $table->decimal('quantity', 18, 6);
            $table->decimal('conversion_to_base', 18, 6)->default(1);
            $table->decimal('quantity_base', 18, 6);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['material_group_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_group_items');
        Schema::dropIfExists('material_groups');
    }
};
