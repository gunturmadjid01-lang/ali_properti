<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->where('name', 'like', 'hpp.%')->delete();
    }

    public function down(): void
    {
        // Permission lama sudah digantikan rab-perumahan.* dan rab-unit.*.
    }
};
