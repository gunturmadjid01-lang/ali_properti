<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_kredits', function (Blueprint $table) {
            $table->id();
            $table->string('kode_bank')->unique();
            $table->string('nama_bank');
            $table->string('nama_pic')->nullable();
            $table->string('telepon_pic')->nullable();
            $table->string('email_pic')->nullable();
            $table->string('status')->default('aktif');
            $table->string('record_status')->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_kredits');
    }
};
