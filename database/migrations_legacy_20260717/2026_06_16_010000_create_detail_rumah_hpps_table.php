<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detail_rumah_hpps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('detail_rumah_id')->constrained('detail_rumahs')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('tanggal_dibuat');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('detail_rumah_hpp_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('detail_rumah_hpp_id')->constrained('detail_rumah_hpps')->cascadeOnDelete();
            $table->foreignId('kelompok_hpp_id')->constrained('kelompok_hpps')->cascadeOnDelete();
            $table->decimal('volume', 16, 2);
            $table->string('satuan');
            $table->decimal('harga_satuan', 16, 2);
            $table->decimal('jumlah_rab', 16, 2);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detail_rumah_hpp_items');
        Schema::dropIfExists('detail_rumah_hpps');
    }
};
