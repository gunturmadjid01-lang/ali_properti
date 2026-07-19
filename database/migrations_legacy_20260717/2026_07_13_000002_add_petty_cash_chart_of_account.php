<?php

use App\Models\ChartOfAccount;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $account = ChartOfAccount::withTrashed()->updateOrCreate(
            ['kode_akun' => ChartOfAccount::KAS_KECIL],
            [
                'nama_akun' => 'Kas Kecil',
                'kategori' => 'aset',
                'posisi_normal' => 'debit',
                'status' => 'aktif',
                'is_system' => true,
            ],
        );

        if ($account->trashed()) {
            $account->restore();
        }
    }

    public function down(): void
    {
        ChartOfAccount::query()->where('kode_akun', ChartOfAccount::KAS_KECIL)->delete();
    }
};
