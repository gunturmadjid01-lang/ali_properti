<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_assets', function (Blueprint $table): void {
            $table->id();
            $table->string('kode_aset')->unique();
            $table->string('nama_aset');
            $table->string('kategori')->default('alat_kecil');
            $table->string('tipe_aset')->default('alat_kecil');
            $table->string('nomor_seri')->nullable();
            $table->string('plat_nomor')->nullable();
            $table->string('lokasi_sekarang')->nullable();
            $table->string('kondisi')->default('baik');
            $table->string('status')->default('tersedia');
            $table->decimal('nilai_aset', 18, 2)->default(0);
            $table->decimal('hour_meter_terakhir', 12, 2)->default(0);
            $table->decimal('odometer_terakhir', 12, 2)->default(0);
            $table->foreignId('penanggung_jawab_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('catatan')->nullable();
            $table->string('record_status')->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('asset_usage_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('kode_pengajuan')->unique();
            $table->foreignId('office_asset_id')->constrained('office_assets')->cascadeOnDelete();
            $table->foreignId('perumahan_id')->nullable()->constrained('perumahans')->nullOnDelete();
            $table->foreignId('detail_rumah_id')->nullable()->constrained('detail_rumahs')->nullOnDelete();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai_estimasi')->nullable();
            $table->text('tujuan_pemakaian');
            $table->string('lokasi_pemakaian')->nullable();
            $table->string('status')->default('diajukan');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('asset_usage_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('kode_log')->unique();
            $table->foreignId('office_asset_id')->constrained('office_assets')->cascadeOnDelete();
            $table->foreignId('asset_usage_request_id')->nullable()->constrained('asset_usage_requests')->nullOnDelete();
            $table->foreignId('used_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('mulai_pakai');
            $table->dateTime('selesai_pakai')->nullable();
            $table->decimal('durasi_jam', 10, 2)->default(0);
            $table->decimal('hour_meter_awal', 12, 2)->default(0);
            $table->decimal('hour_meter_akhir', 12, 2)->default(0);
            $table->decimal('odometer_awal', 12, 2)->default(0);
            $table->decimal('odometer_akhir', 12, 2)->default(0);
            $table->decimal('bbm_liter', 10, 2)->default(0);
            $table->decimal('biaya_bbm', 18, 2)->default(0);
            $table->string('operator')->nullable();
            $table->string('kondisi_sebelum')->nullable();
            $table->string('kondisi_sesudah')->nullable();
            $table->string('lokasi')->nullable();
            $table->text('pekerjaan')->nullable();
            $table->text('catatan')->nullable();
            $table->string('record_status')->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('asset_maintenance_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('kode_servis')->unique();
            $table->foreignId('office_asset_id')->constrained('office_assets')->cascadeOnDelete();
            $table->date('tanggal_servis');
            $table->string('jenis_servis')->default('rutin');
            $table->decimal('hour_meter', 12, 2)->default(0);
            $table->decimal('odometer', 12, 2)->default(0);
            $table->text('pekerjaan_servis');
            $table->text('sparepart')->nullable();
            $table->decimal('biaya', 18, 2)->default(0);
            $table->string('teknisi')->nullable();
            $table->date('jadwal_berikutnya')->nullable();
            $table->string('status')->default('selesai');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_maintenance_logs');
        Schema::dropIfExists('asset_usage_logs');
        Schema::dropIfExists('asset_usage_requests');
        Schema::dropIfExists('office_assets');
    }
};
