<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpr_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpr_submission_id')->constrained('kpr_submissions')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('tanggal_follow_up');
            $table->string('metode_follow_up');
            $table->string('status_kpr');
            $table->text('catatan')->nullable();
            $table->date('rencana_follow_up_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpr_follow_ups');
    }
};
