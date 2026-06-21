<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\Marketing\MarketingOperationsService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (): void {
    app(MarketingOperationsService::class)->syncAutomaticReminders();
})->hourly()->name('marketing-reminders-sync')->withoutOverlapping();

Schedule::call(function (): void {
    app(MarketingOperationsService::class)->expireBookings();
})->dailyAt('00:15')->name('marketing-booking-expiry')->withoutOverlapping();
