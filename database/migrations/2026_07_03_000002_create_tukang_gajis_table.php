<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tukang_gajis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tukang_id')->constrained('tukangs')->cascadeOnDelete();
            $table->decimal('nominal', 16, 2);
            $table->date('tanggal_berlaku');
            $table->string('status')->default('aktif');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tukang_id', 'status', 'tanggal_berlaku']);
        });

        DB::table('tukangs')
            ->whereNull('deleted_at')
            ->where('gaji', '>', 0)
            ->orderBy('id')
            ->each(function (object $tukang): void {
                DB::table('tukang_gajis')->insert([
                    'tukang_id' => $tukang->id,
                    'nominal' => $tukang->gaji,
                    'tanggal_berlaku' => now()->toDateString(),
                    'status' => 'aktif',
                    'created_by' => $tukang->created_by,
                    'updated_by' => $tukang->updated_by,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('tukang_gajis');
    }
};
