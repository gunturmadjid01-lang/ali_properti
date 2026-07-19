<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tahapan_pembangunans', function (Blueprint $table) {
            $table->id();
            $table->string('nama_tahapan');
            $table->decimal('bobot_persen', 5, 2)->default(0);
            $table->unsignedInteger('urutan')->default(0);
            $table->string('status')->default('aktif');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tahapan_pembangunans');
    }
};
