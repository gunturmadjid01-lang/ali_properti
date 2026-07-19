<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('costumer_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('costumer_id')->constrained('costumers')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('tanggal_follow_up');
            $table->string('metode_follow_up');
            $table->boolean('status_serius')->default(false);
            $table->string('progress_kemampuan');
            $table->text('catatan')->nullable();
            $table->date('rencana_follow_up_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costumer_follow_ups');
    }
};
