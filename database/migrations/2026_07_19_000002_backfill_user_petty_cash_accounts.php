<?php

use App\Models\User;
use App\Services\UserPettyCashService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $service = app(UserPettyCashService::class);

        User::query()->orderBy('id')->chunkById(100, function ($users) use ($service): void {
            foreach ($users as $user) {
                $service->ensureFor($user);
            }
        });
    }

    public function down(): void
    {
        // Rekening dapat memiliki transaksi sehingga tidak aman dihapus saat rollback.
    }
};
