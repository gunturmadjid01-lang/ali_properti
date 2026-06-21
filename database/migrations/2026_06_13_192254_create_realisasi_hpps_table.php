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
        Schema::create('realisasi_hpps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('detail_perumahan_hpp_id')->constrained('detail_perumahan_hpps')->onDelete('cascade');
            $table->date('tanggal');
            $table->float('nominal');
            $table->text('keterangan');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('realisasi_hpps');
    }
};
