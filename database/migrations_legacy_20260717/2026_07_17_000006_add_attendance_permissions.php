<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        foreach (['attendance.view', 'attendance.settings'] as $name) {
            DB::table('permissions')->updateOrInsert(['name' => $name, 'guard_name' => 'web'], ['created_at' => $now, 'updated_at' => $now]);
        }
    }
    public function down(): void { DB::table('permissions')->whereIn('name', ['attendance.view', 'attendance.settings'])->delete(); }
};
