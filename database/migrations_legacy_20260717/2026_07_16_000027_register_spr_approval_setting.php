<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $existing = DB::table('approval_settings')->where(['module_key' => 'spr', 'action' => 'lock'])->first();
        if ($existing) {
            DB::table('approval_settings')->where('id', $existing->id)->update([
                'module_label' => 'Pengajuan SPR', 'is_active' => true, 'updated_at' => now(),
            ]);

            return;
        }

        $managerId = DB::table('roles')->where('name', 'manajer_pimpro')->value('id');
        $ownerId = DB::table('roles')->where('name', 'owner')->value('id');
        $steps = collect([$managerId, $ownerId])->filter()->values()->map(fn ($roleId, $index) => [
            'step' => $index + 1, 'role_ids' => [(int) $roleId],
        ])->all();
        DB::table('approval_settings')->insert([
            'module_key' => 'spr', 'module_label' => 'Pengajuan SPR', 'action' => 'lock',
            'requires_approval' => count($steps) > 0, 'approval_stages' => count($steps),
            'approver_role_ids' => json_encode($steps[0]['role_ids'] ?? []),
            'approval_steps' => json_encode($steps), 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('approval_settings')->where(['module_key' => 'spr', 'action' => 'lock'])->update([
            'module_label' => 'Surat Pemesanan Rumah', 'updated_at' => now(),
        ]);
    }
};
