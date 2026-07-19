<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spk_kontraktor_payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('spk_kontraktor_payments', 'contractor_opname_id')) {
                $table->foreignId('contractor_opname_id')
                    ->nullable()
                    ->after('spk_kontraktor_id')
                    ->constrained('contractor_opnames')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('spk_kontraktor_payments', function (Blueprint $table): void {
            if (Schema::hasColumn('spk_kontraktor_payments', 'contractor_opname_id')) {
                $table->dropConstrainedForeignId('contractor_opname_id');
            }
        });
    }
};
