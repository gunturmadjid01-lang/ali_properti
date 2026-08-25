<?php

use App\Services\AdminSalesWorkQueueService;
use App\Services\CrmNurtureService;
use App\Services\Marketing\MarketingOperationsService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('marketing:detect-overdue', function (MarketingOperationsService $service): int {
    $service->syncAutomaticReminders();
    $expired = $service->expireBookings();
    $this->info("Pengingat marketing disinkronkan; {$expired} booking kedaluwarsa diproses.");

    return 0;
})->purpose('Mendeteksi SLA, tugas, kunjungan, follow-up, dan booking marketing yang jatuh tempo');

Schedule::call(function (): void {
    app(MarketingOperationsService::class)->syncAutomaticReminders();
})->hourly()->name('marketing-reminders-sync')->withoutOverlapping();

Schedule::command('marketing:detect-overdue')->dailyAt('00:10')->withoutOverlapping();

Artisan::command('marketing:nurture-sync', function (CrmNurtureService $service): int {
    $enrolled = $service->sync();
    $processed = $service->processDue();
    $this->info("Tindak lanjut otomatis disinkronkan: {$enrolled} lead diperiksa, {$processed} pengingat dibuat.");
    return 0;
})->purpose('Membuat pengingat bertahap untuk lead yang belum mendapat tindak lanjut');

Schedule::command('marketing:nurture-sync')->hourly()->withoutOverlapping();

Artisan::command('admin-sales:sync-work-queue', function (AdminSalesWorkQueueService $service): int {
    $this->info('Antrean Admin Sales disinkronkan: '.json_encode($service->sync(), JSON_UNESCAPED_UNICODE));

    return 0;
})->purpose('Membuat dan menyelesaikan work item Admin Sales dari kondisi transaksi sumber');

Schedule::command('admin-sales:sync-work-queue')->hourly()->withoutOverlapping();
