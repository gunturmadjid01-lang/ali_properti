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
        Schema::create('detail_perumahan_hpps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('perumahan_hpp_id')->constrained('perumahan_hpps')->onDelete('cascade');
            $table->foreignId('kelompok_hpp_id')->constrained('kelompok_hpps')->onDelete('cascade');
            $table->float('volume', 16,2);
            $table->string('satuan');
            $table->float('harga_satuan');
            $table->float('jumlah_rab');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detail_perumahan_hpps');
    }
};
