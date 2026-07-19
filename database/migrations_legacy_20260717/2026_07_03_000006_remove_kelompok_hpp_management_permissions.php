<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')
            ->where('name', 'kelompok-hpp.manage')
            ->orWhere('name', 'like', 'kelompok-hpp.%')
            ->delete();
    }

    public function down(): void
    {
        // Permission master Kelompok HPP sengaja tidak dipulihkan.
    }
};
