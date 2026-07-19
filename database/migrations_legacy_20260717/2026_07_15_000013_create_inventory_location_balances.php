<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_location_stocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->foreignId('inventory_location_id')->constrained('inventory_locations')->cascadeOnDelete();
            $table->unsignedInteger('total_stock')->default(0);
            $table->unsignedInteger('available_stock')->default(0);
            $table->unsignedInteger('borrowed_stock')->default(0);
            $table->unsignedInteger('damaged_stock')->default(0);
            $table->unsignedInteger('lost_stock')->default(0);
            $table->timestamps();
            $table->unique(['inventory_item_id', 'inventory_location_id'], 'inventory_item_location_unique');
        });

        $defaultLocationId = DB::table('inventory_locations')->whereNull('deleted_at')->orderBy('id')->value('id');
        if (! $defaultLocationId) {
            return;
        }

        DB::table('inventory_items')->whereNull('deleted_at')->where('inventory_type', 'quantity')->orderBy('id')->get()->each(function ($item) use ($defaultLocationId): void {
            DB::table('inventory_location_stocks')->insert([
                'inventory_item_id' => $item->id,
                'inventory_location_id' => $defaultLocationId,
                'total_stock' => $item->total_stock,
                'available_stock' => $item->available_stock,
                'borrowed_stock' => $item->borrowed_stock,
                'damaged_stock' => $item->damaged_stock,
                'lost_stock' => $item->lost_stock,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_location_stocks');
    }
};
