<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sprs', function (Blueprint $table): void {
            $table->string('status')->default('draft')->change();
        });

        DB::table('sprs')
            ->where(fn ($query) => $query->whereNull('record_status')->orWhere('record_status', '!=', 'locked'))
            ->whereIn('status', ['menunggu_manager', 'menunggu_owner', 'menunggu_approval'])
            ->whereNotExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('approval_requests')
                ->whereColumn('approval_requests.model_id', 'sprs.id')
                ->where('approval_requests.model_type', 'App\\Models\\Spr')
                ->where('approval_requests.module_key', 'spr')
                ->where('approval_requests.action', 'lock')
                ->where('approval_requests.status', 'pending'))
            ->update(['status' => 'draft']);
    }

    public function down(): void
    {
        Schema::table('sprs', function (Blueprint $table): void {
            $table->string('status')->default('menunggu_manager')->change();
        });
    }
};
