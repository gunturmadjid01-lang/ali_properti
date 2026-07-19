<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('tipe_posts')
            ->where('nama_post', 'Setoran Modal Owner')
            ->update(['nama_post' => 'Setoran Modal Awal', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('tipe_posts')
            ->where('nama_post', 'Setoran Modal Awal')
            ->update(['nama_post' => 'Setoran Modal Owner', 'updated_at' => now()]);
    }
};
