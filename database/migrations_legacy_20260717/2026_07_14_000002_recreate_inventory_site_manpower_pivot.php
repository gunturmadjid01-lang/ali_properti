<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_manpower_logs') || Schema::hasTable('office_asset_site_manpower_log')) {
            return;
        }

        Schema::create('office_asset_site_manpower_log', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_manpower_log_id')->constrained()->cascadeOnDelete();
            $table->foreignId('office_asset_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['site_manpower_log_id', 'office_asset_id'], 'manpower_asset_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_asset_site_manpower_log');
    }
};
