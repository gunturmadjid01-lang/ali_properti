<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hpp_template_stages', function (Blueprint $table) {
            $table->id();
            $table->string('konteks');
            $table->string('nama_tahapan');
            $table->decimal('bobot_persen', 5, 2)->default(0);
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();
            $table->unique(['konteks', 'nama_tahapan']);
        });

        Schema::create('hpp_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hpp_template_stage_id')->constrained('hpp_template_stages')->cascadeOnDelete();
            $table->foreignId('kelompok_hpp_id')->constrained('kelompok_hpps')->restrictOnDelete();
            $table->string('nama_pekerjaan');
            $table->decimal('volume', 16, 2)->default(0);
            $table->string('satuan')->default('');
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hpp_template_items');
        Schema::dropIfExists('hpp_template_stages');
    }
};
