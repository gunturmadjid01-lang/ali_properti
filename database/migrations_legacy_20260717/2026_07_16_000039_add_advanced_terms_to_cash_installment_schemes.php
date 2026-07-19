<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_installment_schemes', fn (Blueprint $table) => $table->json('advanced_config')->nullable());
    }

    public function down(): void
    {
        Schema::table('cash_installment_schemes', fn (Blueprint $table) => $table->dropColumn('advanced_config'));
    }
};
