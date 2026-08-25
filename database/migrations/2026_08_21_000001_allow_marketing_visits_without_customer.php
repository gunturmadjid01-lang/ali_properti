<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Canvassing dicatat sebagai aktivitas prospek; customer baru ada setelah
     * prospek dikonversi secara manual. Karena itu relasi customer bersifat opsional.
     */
    public function up(): void
    {
        Schema::table('marketing_visits', function (Blueprint $table): void {
            $table->unsignedBigInteger('costumer_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Tidak aman memaksa kolom kembali wajib karena aktivitas prospek tanpa
        // customer yang sudah tersimpan harus tetap dapat dipulihkan.
    }
};
