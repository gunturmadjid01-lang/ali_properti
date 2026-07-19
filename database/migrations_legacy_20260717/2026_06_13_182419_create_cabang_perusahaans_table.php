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
        Schema::create('cabang_perusahaans', function (Blueprint $table) {
            $table->id();
            $table->string('kode_cabang')->unique();
            $table->string('nama_cabang')->unique();
            $table->string('logo')->nullable();
            $table->string('image')->nullable();
            $table->text('address');
            $table->string('phone');
            $table->string('latitude')->nullable();
            $table->string('longtitude')->nullable();
            $table->longText('deskripsi')->nullable();
            $table->string('emaiil');
            $table->string('manager_name');
            $table->string('status');
            $table->string('type')->default('cabang');
            $table->string('record_status')->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cabang_perusahaans');
    }
};
