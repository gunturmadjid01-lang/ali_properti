<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'gudang_id')) {
                $table->foreignId('gudang_id')->nullable()->after('kantor_cabang_id')->constrained('gudangs')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'gudang_id')) {
                $table->dropConstrainedForeignId('gudang_id');
            }
        });
    }
};
