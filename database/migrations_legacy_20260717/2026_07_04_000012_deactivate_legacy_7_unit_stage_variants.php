<?php

use App\Models\TahapanPembangunan;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private array $legacyNames = [
        'I PEKERJAAN PONDASI',
        'II PEKERJAAN KONSTRUKSI DINDING',
        'III PEKERJAAN ATAP & PLAFON',
        'III PEKERJAAN ATAP DAN PLAFON',
        'IV PEKERJAAN KUSEN, PINTU & JENDELA',
        'V PEKERJAAN KERAMIK & SANITARI',
        'V PEKERJAAN KERAMIK, DINDING & SANITARI',
        'VI PEKERJAAN TAMBAHAN',
        'VII PEKERJAAN PENGECATAN',
        'VII PENGECATAN',
    ];

    public function up(): void
    {
        TahapanPembangunan::query()
            ->where('konteks', 'unit')
            ->whereIn('nama_tahapan', $this->legacyNames)
            ->update(['status' => 'nonaktif', 'updated_at' => now()]);
    }

    public function down(): void
    {
        TahapanPembangunan::query()
            ->where('konteks', 'unit')
            ->whereIn('nama_tahapan', $this->legacyNames)
            ->update(['status' => 'aktif', 'updated_at' => now()]);
    }
};
