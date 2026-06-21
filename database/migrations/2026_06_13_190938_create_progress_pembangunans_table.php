<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('progress_pembangunans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('detail_rumah_id')->constrained('detail_rumahs')->onDelete('cascade');
            $table->foreignId('tahapan_pembangunan_id')->nullable()->constrained('tahapan_pembangunans')->nullOnDelete();
            $table->date('tanggal');
            $table->float('tahapan');
            $table->float('persentase');
            $table->decimal('persentase_total', 5, 2)->default(0);
            $table->text('keterangan');
            $table->string('foto')->nullable();
            $table->foreignId('users_id')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('progress_pembangunans');
    }
};
