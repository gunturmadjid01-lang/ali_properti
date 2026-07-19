<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spk_kontraktors', function (Blueprint $table): void {
            if (! Schema::hasColumn('spk_kontraktors', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('updated_by')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('spk_kontraktors', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('spk_kontraktors', function (Blueprint $table): void {
            if (Schema::hasColumn('spk_kontraktors', 'approved_at')) {
                $table->dropColumn('approved_at');
            }

            if (Schema::hasColumn('spk_kontraktors', 'approved_by')) {
                $table->dropConstrainedForeignId('approved_by');
            }
        });
    }
};
