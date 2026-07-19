<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void { DB::table('approval_settings')->updateOrInsert(['module_key' => 'housing-reservation', 'action' => 'lock'], ['module_label' => 'Reservasi Perumahan', 'requires_approval' => false, 'approval_stages' => 0, 'approver_role_ids' => json_encode([]), 'approval_steps' => json_encode([]), 'is_active' => true, 'updated_at' => now(), 'created_at' => now()]); }
    public function down(): void { DB::table('approval_settings')->where('module_key', 'housing-reservation')->delete(); }
};
