<?php

use App\Models\Costumer;
use App\Models\DetailRumah;
use App\Models\HousingReservation;
use App\Services\HousingReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

test('reservasi tetap privat sebagai draft lalu menerbitkan invoice dan menahan unit saat lock', function () {
    $customer = Costumer::factory()->create();
    $unit = DetailRumah::factory()->create(['status_penjualan' => 'tersedia']);
    $reservation = app(HousingReservationService::class)->create([
        'costumer_id' => $customer->id, 'detail_rumah_id' => $unit->id,
        'payment_method' => 'cash', 'booking_fee' => 5000000, 'payment_due_at' => now()->addDay(),
    ]);
    expect($reservation->invoice_no)->toStartWith('INV-BF/')
        ->and($reservation->record_status)->toBe('draft')
        ->and($unit->fresh()->status_penjualan)->toBe('tersedia')
        ->and($reservation->paymentSchedule)->toBeNull();

    $reservation = app(HousingReservationService::class)->lock($reservation);
    $reservation = app(HousingReservationService::class)->finalize($reservation);
    expect($reservation->record_status)->toBe('locked')
        ->and($unit->fresh()->status_penjualan)->toBe('booking')
        ->and($reservation->paymentSchedule)->not->toBeNull();

    expect(fn () => app(HousingReservationService::class)->cancel($reservation, 'Customer membatalkan', 'customer'))
        ->toThrow(ValidationException::class);
    expect($reservation->fresh()->status)->toBe('pending_approval')
        ->and($unit->fresh()->status_penjualan)->toBe('booking');
});

test('pembatalan manual hanya tersedia sebelum reservasi dikunci', function () {
    $customer = Costumer::factory()->create();
    $unit = DetailRumah::factory()->create(['status_penjualan' => 'tersedia']);
    $reservation = app(HousingReservationService::class)->create([
        'costumer_id' => $customer->id,
        'detail_rumah_id' => $unit->id,
        'payment_method' => 'cash',
        'booking_fee' => 5000000,
        'payment_due_at' => now()->addDay(),
    ]);

    app(HousingReservationService::class)->cancel($reservation, 'Customer tidak melanjutkan', 'customer');

    expect($reservation->fresh()->status)->toBe('customer_cancelled');
});

test('reservasi yang melewati batas bayar kedaluwarsa otomatis', function () {
    $customer = Costumer::factory()->create();
    $unit = DetailRumah::factory()->create(['status_penjualan' => 'tersedia']);
    $reservation = HousingReservation::create([
        'reservation_no' => 'RSV/TEST/1', 'invoice_no' => 'INV-BF/TEST/1',
        'costumer_id' => $customer->id, 'detail_rumah_id' => $unit->id,
        'booking_fee' => 5000000, 'reserved_at' => now()->subDays(2),
        'payment_due_at' => now()->subDay(), 'status' => 'active', 'payment_status' => 'unpaid',
    ]);
    $unit->update(['status_penjualan' => 'booking']);

    expect(app(HousingReservationService::class)->expireUnpaid())->toBe(1)
        ->and($reservation->fresh()->status)->toBe('expired')
        ->and($unit->fresh()->status_penjualan)->toBe('tersedia');
});
