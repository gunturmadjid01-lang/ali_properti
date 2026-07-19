<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gudang_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('gudang_id')->constrained('gudangs')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'gudang_id']);
        });

        if (Schema::hasColumn('users', 'gudang_id')) {
            $now = now();
            DB::table('users')
                ->whereNotNull('gudang_id')
                ->orderBy('id')
                ->get(['id', 'gudang_id'])
                ->each(function ($user) use ($now): void {
                    DB::table('gudang_user')->updateOrInsert(
                        ['user_id' => $user->id, 'gudang_id' => $user->gudang_id],
                        ['created_at' => $now, 'updated_at' => $now],
                    );
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gudang_user');
    }
};
