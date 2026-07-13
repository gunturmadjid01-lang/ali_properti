<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hpp_realisasis', function (Blueprint $table): void {
            if (! Schema::hasColumn('hpp_realisasis', 'source_type')) {
                $table->string('source_type')->nullable()->after('kelompok_hpp_id');
            }

            if (! Schema::hasColumn('hpp_realisasis', 'source_id')) {
                $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            }

            $table->index(['source_type', 'source_id'], 'hpp_realisasis_source_index');
        });
    }

    public function down(): void
    {
        Schema::table('hpp_realisasis', function (Blueprint $table): void {
            $table->dropIndex('hpp_realisasis_source_index');

            if (Schema::hasColumn('hpp_realisasis', 'source_id')) {
                $table->dropColumn('source_id');
            }

            if (Schema::hasColumn('hpp_realisasis', 'source_type')) {
                $table->dropColumn('source_type');
            }
        });
    }
};
