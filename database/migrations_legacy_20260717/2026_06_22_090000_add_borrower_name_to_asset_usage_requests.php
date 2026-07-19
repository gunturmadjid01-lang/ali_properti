<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_usage_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('asset_usage_requests', 'nama_peminjam')) {
                $table->string('nama_peminjam')->nullable()->after('detail_rumah_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('asset_usage_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('asset_usage_requests', 'nama_peminjam')) {
                $table->dropColumn('nama_peminjam');
            }
        });
    }
};
