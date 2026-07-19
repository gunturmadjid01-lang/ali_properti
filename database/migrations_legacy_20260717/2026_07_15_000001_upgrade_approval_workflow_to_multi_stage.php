<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('approval_stages')->default(0)->after('requires_approval');
            $table->json('approval_steps')->nullable()->after('approver_role_ids');
        });

        Schema::table('approval_requests', function (Blueprint $table) {
            $table->unsignedTinyInteger('current_step')->default(1)->after('status');
            $table->unsignedTinyInteger('total_steps')->default(1)->after('current_step');
            $table->json('step_history')->nullable()->after('total_steps');
        });

        DB::table('approval_settings')->orderBy('id')->each(function ($setting) {
            $roles = json_decode($setting->approver_role_ids ?: '[]', true) ?: [];
            $enabled = (bool) $setting->requires_approval;

            DB::table('approval_settings')->where('id', $setting->id)->update([
                'approval_stages' => $enabled ? 1 : 0,
                'approval_steps' => $enabled ? json_encode([['step' => 1, 'role_ids' => $roles]]) : json_encode([]),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('approval_requests', function (Blueprint $table) {
            $table->dropColumn(['current_step', 'total_steps', 'step_history']);
        });
        Schema::table('approval_settings', function (Blueprint $table) {
            $table->dropColumn(['approval_stages', 'approval_steps']);
        });
    }
};
