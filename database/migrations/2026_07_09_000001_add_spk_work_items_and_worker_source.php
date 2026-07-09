<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spk_kontraktors', function (Blueprint $table): void {
            if (! Schema::hasColumn('spk_kontraktors', 'sumber_tenaga_kerja')) {
                $table->string('sumber_tenaga_kerja')->default('tukang_owner')->after('jenis_pekerjaan');
            }
        });

        Schema::create('spk_kontraktor_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('spk_kontraktor_id')->constrained('spk_kontraktors')->cascadeOnDelete();
            $table->foreignId('tahapan_pembangunan_id')->nullable()->constrained('tahapan_pembangunans')->nullOnDelete();
            $table->string('nama_tahap_pekerjaan');
            $table->string('nama_pekerjaan');
            $table->decimal('volume', 16, 2)->default(0);
            $table->string('satuan')->default('');
            $table->decimal('harga_satuan', 16, 2)->default(0);
            $table->decimal('total', 16, 2)->default(0);
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('spk_kontraktor_payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('spk_kontraktor_payments', 'spk_kontraktor_item_id')) {
                $table->foreignId('spk_kontraktor_item_id')->nullable()->after('contractor_opname_id')->constrained('spk_kontraktor_items')->nullOnDelete();
            }
            if (! Schema::hasColumn('spk_kontraktor_payments', 'pekerjaan')) {
                $table->string('pekerjaan')->nullable()->after('spk_kontraktor_item_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('spk_kontraktor_payments', function (Blueprint $table): void {
            if (Schema::hasColumn('spk_kontraktor_payments', 'spk_kontraktor_item_id')) {
                $table->dropConstrainedForeignId('spk_kontraktor_item_id');
            }
            if (Schema::hasColumn('spk_kontraktor_payments', 'pekerjaan')) {
                $table->dropColumn('pekerjaan');
            }
        });

        Schema::dropIfExists('spk_kontraktor_items');

        Schema::table('spk_kontraktors', function (Blueprint $table): void {
            if (Schema::hasColumn('spk_kontraktors', 'sumber_tenaga_kerja')) {
                $table->dropColumn('sumber_tenaga_kerja');
            }
        });
    }
};
