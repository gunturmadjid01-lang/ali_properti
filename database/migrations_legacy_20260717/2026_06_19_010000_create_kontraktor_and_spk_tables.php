<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kontraktors', function (Blueprint $table) {
            $table->id();
            $table->string('kode_kontraktor')->unique();
            $table->string('nama_kontraktor');
            $table->string('jenis_badan')->nullable();
            $table->string('bidang_pekerjaan')->nullable();
            $table->string('penanggung_jawab')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('alamat')->nullable();
            $table->text('catatan')->nullable();
            $table->string('status')->default('aktif');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('spk_kontraktors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kontraktor_id')->constrained('kontraktors')->cascadeOnDelete();
            $table->foreignId('perumahan_id')->nullable()->constrained('perumahans')->nullOnDelete();
            $table->foreignId('detail_rumah_id')->nullable()->constrained('detail_rumahs')->nullOnDelete();
            $table->string('nomor_spk')->unique();
            $table->string('judul_pekerjaan');
            $table->string('jenis_pekerjaan')->default('rumah');
            $table->date('tanggal_spk');
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
            $table->decimal('nilai_kontrak', 16, 2)->default(0);
            $table->string('metode_pembayaran')->default('cash');
            $table->text('lingkup_pekerjaan')->nullable();
            $table->text('catatan')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('spk_kontraktor_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spk_kontraktor_id')->constrained('spk_kontraktors')->cascadeOnDelete();
            $table->unsignedSmallInteger('termin_ke')->default(1);
            $table->date('tanggal_pembayaran')->nullable();
            $table->decimal('nominal', 16, 2)->default(0);
            $table->text('keterangan')->nullable();
            $table->string('status')->default('menunggu_pengajuan');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spk_kontraktor_payments');
        Schema::dropIfExists('spk_kontraktors');
        Schema::dropIfExists('kontraktors');
    }
};
