<?php

use App\Models\HousingReservation;
use App\Models\SalesTransaction;
use App\Services\SalesProcessService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        HousingReservation::query()
            ->with('unit')
            ->where('record_status', 'locked')
            ->whereNotIn('status', ['cancelled', 'customer_cancelled', 'expired'])
            ->chunkById(100, function ($reservations): void {
                foreach ($reservations as $reservation) {
                    if (in_array($reservation->unit?->status_penjualan, [null, 'tersedia', 'available'], true)) {
                        $reservation->unit->update([
                            'status_penjualan' => 'booking',
                            'booking_at' => $reservation->unit->booking_at ?? $reservation->reserved_at ?? now(),
                        ]);
                    }
                }
            });

        SalesTransaction::query()
            ->with(['housingUnit', 'customer', 'processSteps'])
            ->whereNotIn('status', ['cancelled', 'closed_lost'])
            ->chunkById(100, function ($transactions): void {
                foreach ($transactions as $transaction) {
                    app(SalesProcessService::class)->syncCommercialUnitStatus($transaction);
                }
            });
    }

    public function down(): void
    {
        // Rekonsiliasi mengikuti milestone bisnis dan tidak aman untuk dibalik otomatis.
    }
};
