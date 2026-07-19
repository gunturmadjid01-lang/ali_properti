<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('field_defects', function (Blueprint $table) {
            if (! Schema::hasColumn('field_defects', 'progress_pembangunan_id')) {
                $table->foreignId('progress_pembangunan_id')->nullable()->after('tahapan_pembangunan_id')->constrained('progress_pembangunans')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('field_defects', function (Blueprint $table) {
            if (Schema::hasColumn('field_defects', 'progress_pembangunan_id')) {
                $table->dropConstrainedForeignId('progress_pembangunan_id');
            }
        });
    }
};
