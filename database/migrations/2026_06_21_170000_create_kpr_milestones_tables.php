<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpr_milestones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('kpr_submission_id')->constrained('kpr_submissions')->cascadeOnDelete();
            $table->string('jenis');
            $table->dateTime('tanggal_proses');
            $table->string('lokasi')->nullable();
            $table->string('nomor_dokumen')->nullable();
            $table->string('pihak_terkait')->nullable();
            $table->text('catatan')->nullable();
            $table->string('record_status')->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['kpr_submission_id', 'jenis']);
        });

        Schema::create('kpr_milestone_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('kpr_milestone_id')->constrained('kpr_milestones')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nama_file');
            $table->string('path_file');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->text('keterangan')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpr_milestone_documents');
        Schema::dropIfExists('kpr_milestones');
    }
};
