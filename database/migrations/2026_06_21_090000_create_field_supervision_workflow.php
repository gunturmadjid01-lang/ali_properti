<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_requests', function (Blueprint $table) {
            $table->foreignId('approved_by_owner')->nullable()->after('approved_at_gudang')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at_owner')->nullable()->after('approved_by_owner');
            $table->text('owner_approval_note')->nullable()->after('approved_at_owner');
            $table->foreignId('issued_by')->nullable()->after('owner_approval_note')->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable()->after('issued_by');
            $table->foreignId('transaksi_logistik_id')->nullable()->after('issued_at')->constrained('transaksi_logistiks')->nullOnDelete();
        });

        Schema::table('material_request_details', function (Blueprint $table) {
            $table->decimal('qty_issued', 16, 2)->default(0)->after('qty');
        });

        Schema::table('transaksi_logistiks', function (Blueprint $table) {
            $table->string('source_type')->nullable()->after('keterangan');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('site_material_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gudang_id')->constrained('gudangs')->cascadeOnDelete();
            $table->foreignId('perumahan_id')->constrained('perumahans')->cascadeOnDelete();
            $table->foreignId('detail_rumah_id')->nullable()->constrained('detail_rumahs')->cascadeOnDelete();
            $table->foreignId('tahapan_pembangunan_id')->nullable()->constrained('tahapan_pembangunans')->nullOnDelete();
            $table->foreignId('barang_material_id')->constrained('barang_materials')->cascadeOnDelete();
            $table->decimal('qty_received', 16, 2)->default(0);
            $table->decimal('qty_used', 16, 2)->default(0);
            $table->decimal('qty_returned', 16, 2)->default(0);
            $table->decimal('qty_available', 16, 2)->default(0);
            $table->timestamps();

            $table->unique(
                ['gudang_id', 'perumahan_id', 'detail_rumah_id', 'tahapan_pembangunan_id', 'barang_material_id'],
                'site_material_stock_unique'
            );
        });

        Schema::create('material_usages', function (Blueprint $table) {
            $table->id();
            $table->string('kode_pemakaian')->unique();
            $table->date('tanggal');
            $table->foreignId('perumahan_id')->constrained('perumahans')->cascadeOnDelete();
            $table->foreignId('detail_rumah_id')->nullable()->constrained('detail_rumahs')->cascadeOnDelete();
            $table->foreignId('tahapan_pembangunan_id')->nullable()->constrained('tahapan_pembangunans')->nullOnDelete();
            $table->foreignId('progress_pembangunan_id')->nullable()->constrained('progress_pembangunans')->nullOnDelete();
            $table->text('keterangan')->nullable();
            $table->string('foto')->nullable();
            $table->string('record_status')->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('material_usage_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_usage_id')->constrained('material_usages')->cascadeOnDelete();
            $table->foreignId('site_material_stock_id')->constrained('site_material_stocks')->cascadeOnDelete();
            $table->foreignId('barang_material_id')->constrained('barang_materials')->cascadeOnDelete();
            $table->decimal('qty', 16, 2);
            $table->string('satuan');
            $table->timestamps();
        });

        Schema::create('material_returns', function (Blueprint $table) {
            $table->id();
            $table->string('kode_pengembalian')->unique();
            $table->date('tanggal');
            $table->foreignId('gudang_id')->constrained('gudangs')->cascadeOnDelete();
            $table->foreignId('perumahan_id')->constrained('perumahans')->cascadeOnDelete();
            $table->foreignId('detail_rumah_id')->nullable()->constrained('detail_rumahs')->cascadeOnDelete();
            $table->foreignId('tahapan_pembangunan_id')->nullable()->constrained('tahapan_pembangunans')->nullOnDelete();
            $table->string('status')->default('diajukan');
            $table->text('keterangan')->nullable();
            $table->text('receive_note')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('transaksi_logistik_id')->nullable()->constrained('transaksi_logistiks')->nullOnDelete();
            $table->string('record_status')->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('material_return_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_return_id')->constrained('material_returns')->cascadeOnDelete();
            $table->foreignId('site_material_stock_id')->constrained('site_material_stocks')->cascadeOnDelete();
            $table->foreignId('barang_material_id')->constrained('barang_materials')->cascadeOnDelete();
            $table->decimal('qty', 16, 2);
            $table->string('satuan');
            $table->decimal('harga_satuan', 16, 2)->default(0);
            $table->decimal('subtotal', 16, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('site_reports', function (Blueprint $table) {
            $table->id();
            $table->string('kode_laporan')->unique();
            $table->string('jenis_laporan')->default('harian');
            $table->date('tanggal');
            $table->date('periode_mulai')->nullable();
            $table->date('periode_selesai')->nullable();
            $table->foreignId('perumahan_id')->constrained('perumahans')->cascadeOnDelete();
            $table->foreignId('detail_rumah_id')->nullable()->constrained('detail_rumahs')->cascadeOnDelete();
            $table->foreignId('tahapan_pembangunan_id')->nullable()->constrained('tahapan_pembangunans')->nullOnDelete();
            $table->string('cuaca')->nullable();
            $table->unsignedInteger('jumlah_pekerja')->default(0);
            $table->string('kontraktor')->nullable();
            $table->text('pekerjaan_selesai');
            $table->text('pekerjaan_tertahan')->nullable();
            $table->text('kendala')->nullable();
            $table->text('koordinasi')->nullable();
            $table->text('rencana_berikutnya')->nullable();
            $table->string('lampiran')->nullable();
            $table->string('approval_status')->default('menunggu_approval_manager');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('record_status')->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('quality_inspections', function (Blueprint $table) {
            $table->id();
            $table->string('kode_inspeksi')->unique();
            $table->date('tanggal');
            $table->foreignId('perumahan_id')->constrained('perumahans')->cascadeOnDelete();
            $table->foreignId('detail_rumah_id')->nullable()->constrained('detail_rumahs')->cascadeOnDelete();
            $table->foreignId('tahapan_pembangunan_id')->nullable()->constrained('tahapan_pembangunans')->nullOnDelete();
            $table->string('hasil');
            $table->text('item_pemeriksaan');
            $table->text('temuan')->nullable();
            $table->text('tindakan_perbaikan')->nullable();
            $table->date('target_selesai')->nullable();
            $table->string('foto')->nullable();
            $table->string('status')->default('terbuka');
            $table->string('approval_status')->default('menunggu_approval_manager');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('record_status')->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('site_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('kode_jadwal')->unique();
            $table->foreignId('perumahan_id')->constrained('perumahans')->cascadeOnDelete();
            $table->foreignId('detail_rumah_id')->nullable()->constrained('detail_rumahs')->cascadeOnDelete();
            $table->foreignId('tahapan_pembangunan_id')->nullable()->constrained('tahapan_pembangunans')->nullOnDelete();
            $table->string('nama_pekerjaan');
            $table->date('tanggal_mulai');
            $table->date('tanggal_target');
            $table->decimal('target_progress', 5, 2)->default(100);
            $table->decimal('realisasi_progress', 5, 2)->default(0);
            $table->string('status')->default('direncanakan');
            $table->text('kendala')->nullable();
            $table->text('catatan')->nullable();
            $table->string('record_status')->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_schedules');
        Schema::dropIfExists('quality_inspections');
        Schema::dropIfExists('site_reports');
        Schema::dropIfExists('material_return_details');
        Schema::dropIfExists('material_returns');
        Schema::dropIfExists('material_usage_details');
        Schema::dropIfExists('material_usages');
        Schema::dropIfExists('site_material_stocks');

        Schema::table('transaksi_logistiks', function (Blueprint $table) {
            $table->dropIndex(['source_type', 'source_id']);
            $table->dropColumn(['source_type', 'source_id']);
        });

        Schema::table('material_request_details', function (Blueprint $table) {
            $table->dropColumn('qty_issued');
        });

        Schema::table('material_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('transaksi_logistik_id');
            $table->dropConstrainedForeignId('issued_by');
            $table->dropColumn('issued_at');
            $table->dropColumn('owner_approval_note');
            $table->dropColumn('approved_at_owner');
            $table->dropConstrainedForeignId('approved_by_owner');
        });
    }
};
