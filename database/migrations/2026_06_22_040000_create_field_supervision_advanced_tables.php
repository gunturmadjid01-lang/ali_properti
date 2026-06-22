<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_defects', function (Blueprint $table) {
            $table->id();
            $table->string('kode_defect')->unique();
            $table->date('tanggal');
            $table->foreignId('perumahan_id')->constrained('perumahans')->cascadeOnDelete();
            $table->foreignId('detail_rumah_id')->nullable()->constrained('detail_rumahs')->cascadeOnDelete();
            $table->foreignId('tahapan_pembangunan_id')->nullable()->constrained('tahapan_pembangunans')->nullOnDelete();
            $table->foreignId('quality_inspection_id')->nullable()->constrained('quality_inspections')->nullOnDelete();
            $table->string('kategori')->default('pekerjaan');
            $table->string('prioritas')->default('medium');
            $table->text('temuan');
            $table->text('instruksi_perbaikan')->nullable();
            $table->date('target_selesai')->nullable();
            $table->date('tanggal_selesai')->nullable();
            $table->string('status')->default('open');
            $table->string('foto')->nullable();
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

        Schema::create('contractor_opnames', function (Blueprint $table) {
            $table->id();
            $table->string('kode_opname')->unique();
            $table->date('tanggal');
            $table->foreignId('spk_kontraktor_id')->nullable()->constrained('spk_kontraktors')->nullOnDelete();
            $table->foreignId('perumahan_id')->constrained('perumahans')->cascadeOnDelete();
            $table->foreignId('detail_rumah_id')->nullable()->constrained('detail_rumahs')->cascadeOnDelete();
            $table->foreignId('tahapan_pembangunan_id')->nullable()->constrained('tahapan_pembangunans')->nullOnDelete();
            $table->foreignId('progress_pembangunan_id')->nullable()->constrained('progress_pembangunans')->nullOnDelete();
            $table->string('pekerjaan');
            $table->decimal('progress_diakui', 5, 2)->default(0);
            $table->decimal('nilai_diajukan', 18, 2)->default(0);
            $table->decimal('nilai_disetujui', 18, 2)->default(0);
            $table->text('catatan')->nullable();
            $table->string('status')->default('diajukan');
            $table->string('foto')->nullable();
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

        Schema::create('work_change_requests', function (Blueprint $table) {
            $table->id();
            $table->string('kode_perubahan')->unique();
            $table->date('tanggal');
            $table->foreignId('perumahan_id')->constrained('perumahans')->cascadeOnDelete();
            $table->foreignId('detail_rumah_id')->nullable()->constrained('detail_rumahs')->cascadeOnDelete();
            $table->foreignId('tahapan_pembangunan_id')->nullable()->constrained('tahapan_pembangunans')->nullOnDelete();
            $table->foreignId('spk_kontraktor_id')->nullable()->constrained('spk_kontraktors')->nullOnDelete();
            $table->string('jenis_perubahan')->default('pekerjaan_tambah');
            $table->text('uraian_perubahan');
            $table->text('alasan')->nullable();
            $table->decimal('estimasi_biaya', 18, 2)->default(0);
            $table->unsignedSmallInteger('estimasi_hari')->default(0);
            $table->string('status')->default('diajukan');
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

        Schema::create('site_manpower_logs', function (Blueprint $table) {
            $table->id();
            $table->string('kode_log')->unique();
            $table->date('tanggal');
            $table->foreignId('perumahan_id')->constrained('perumahans')->cascadeOnDelete();
            $table->foreignId('detail_rumah_id')->nullable()->constrained('detail_rumahs')->cascadeOnDelete();
            $table->foreignId('spk_kontraktor_id')->nullable()->constrained('spk_kontraktors')->nullOnDelete();
            $table->string('kontraktor')->nullable();
            $table->unsignedInteger('mandor')->default(0);
            $table->unsignedInteger('tukang')->default(0);
            $table->unsignedInteger('kenek')->default(0);
            $table->decimal('jam_kerja', 5, 2)->default(8);
            $table->decimal('jam_lembur', 5, 2)->default(0);
            $table->text('alat_digunakan')->nullable();
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

        Schema::create('safety_reports', function (Blueprint $table) {
            $table->id();
            $table->string('kode_k3')->unique();
            $table->date('tanggal');
            $table->foreignId('perumahan_id')->constrained('perumahans')->cascadeOnDelete();
            $table->foreignId('detail_rumah_id')->nullable()->constrained('detail_rumahs')->cascadeOnDelete();
            $table->string('kategori')->default('checklist');
            $table->string('tingkat_risiko')->default('low');
            $table->text('temuan');
            $table->text('tindakan')->nullable();
            $table->string('status')->default('open');
            $table->string('foto')->nullable();
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

        Schema::create('internal_handovers', function (Blueprint $table) {
            $table->id();
            $table->string('kode_serah_terima')->unique();
            $table->date('tanggal');
            $table->foreignId('perumahan_id')->constrained('perumahans')->cascadeOnDelete();
            $table->foreignId('detail_rumah_id')->constrained('detail_rumahs')->cascadeOnDelete();
            $table->decimal('progress_unit', 5, 2)->default(0);
            $table->string('kondisi_bangunan')->default('siap_review');
            $table->text('checklist')->nullable();
            $table->text('catatan')->nullable();
            $table->string('status')->default('diajukan');
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
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_handovers');
        Schema::dropIfExists('safety_reports');
        Schema::dropIfExists('site_manpower_logs');
        Schema::dropIfExists('work_change_requests');
        Schema::dropIfExists('contractor_opnames');
        Schema::dropIfExists('field_defects');
    }
};
