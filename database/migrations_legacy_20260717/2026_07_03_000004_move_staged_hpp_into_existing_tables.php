<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('rab_kawasan_items');
        Schema::dropIfExists('rab_unit_items');
        Schema::dropIfExists('rab_unit_templates');

        if (Schema::hasColumn('tahapan_pembangunans', 'is_rab')) {
            Schema::table('tahapan_pembangunans', function (Blueprint $table) {
                $table->dropColumn('is_rab');
            });
        }

        Schema::table('detail_perumahan_hpps', function (Blueprint $table) {
            if (! Schema::hasColumn('detail_perumahan_hpps', 'tahapan_pembangunan_id')) {
                $table->foreignId('tahapan_pembangunan_id')->nullable()->after('kelompok_hpp_id')->constrained('tahapan_pembangunans')->nullOnDelete();
            }

            if (! Schema::hasColumn('detail_perumahan_hpps', 'nama_pekerjaan')) {
                $table->string('nama_pekerjaan')->nullable()->after('tahapan_pembangunan_id');
            }

            if (! Schema::hasColumn('detail_perumahan_hpps', 'urutan')) {
                $table->unsignedInteger('urutan')->default(0)->after('nama_pekerjaan');
            }
        });

        Schema::table('detail_rumah_hpp_items', function (Blueprint $table) {
            if (! Schema::hasColumn('detail_rumah_hpp_items', 'tahapan_pembangunan_id')) {
                $table->foreignId('tahapan_pembangunan_id')->nullable()->after('kelompok_hpp_id')->constrained('tahapan_pembangunans')->nullOnDelete();
            }

            if (! Schema::hasColumn('detail_rumah_hpp_items', 'nama_pekerjaan')) {
                $table->string('nama_pekerjaan')->nullable()->after('tahapan_pembangunan_id');
            }

            if (! Schema::hasColumn('detail_rumah_hpp_items', 'urutan')) {
                $table->unsignedInteger('urutan')->default(0)->after('nama_pekerjaan');
            }
        });
    }

    public function down(): void
    {
        Schema::table('detail_rumah_hpp_items', function (Blueprint $table) {
            foreach (['tahapan_pembangunan_id', 'nama_pekerjaan', 'urutan'] as $column) {
                if (Schema::hasColumn('detail_rumah_hpp_items', $column)) {
                    $column === 'tahapan_pembangunan_id'
                        ? $table->dropConstrainedForeignId($column)
                        : $table->dropColumn($column);
                }
            }
        });

        Schema::table('detail_perumahan_hpps', function (Blueprint $table) {
            foreach (['tahapan_pembangunan_id', 'nama_pekerjaan', 'urutan'] as $column) {
                if (Schema::hasColumn('detail_perumahan_hpps', $column)) {
                    $column === 'tahapan_pembangunan_id'
                        ? $table->dropConstrainedForeignId($column)
                        : $table->dropColumn($column);
                }
            }
        });
    }
};
