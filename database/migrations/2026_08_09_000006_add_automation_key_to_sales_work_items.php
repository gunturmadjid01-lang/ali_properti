<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_work_items', fn (Blueprint $table) => $table->string('automation_key')->nullable()->unique()->after('work_no'));
    }

    public function down(): void
    {
        Schema::table('sales_work_items', fn (Blueprint $table) => $table->dropUnique(['automation_key'])->dropColumn('automation_key'));
    }
};
