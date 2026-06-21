<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpr_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('kode_kpr')->unique();
            $table->foreignId('spr_id')->constrained('sprs')->onDelete('cascade');
            $table->foreignId('bank_kredit_id')->nullable()->constrained('bank_kredits')->nullOnDelete();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('tanggal_pengajuan')->nullable();
            $table->decimal('nilai_pengajuan', 18, 2)->default(0);
            $table->string('status')->default('pengumpulan_dokumen');
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpr_submissions');
    }
};
