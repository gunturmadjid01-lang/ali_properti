<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spr_berkas_costumers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spr_id')->constrained('sprs')->cascadeOnDelete();
            $table->foreignId('dokumen_costumer_id')->constrained('dokumen_costumers')->cascadeOnDelete();
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
        Schema::dropIfExists('spr_berkas_costumers');
    }
};
