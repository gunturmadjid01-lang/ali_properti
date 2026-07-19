<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('approval_settings')->whereIn('action', ['update', 'delete'])->delete();
    }

    public function down(): void
    {
        // Konfigurasi lama tidak dapat direkonstruksi dengan aman.
    }
};
