<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('progress_pembangunans', function (Blueprint $table): void {
            $table->foreignId('detail_rumah_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('progress_pembangunans', function (Blueprint $table): void {
            $table->foreignId('detail_rumah_id')->nullable(false)->change();
        });
    }
};
