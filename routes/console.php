<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\DataRetentionService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Task Scheduler SIKOMAT AC: Auto-Pruning & Hourly Aggregation harian pada pukul 02:00 WIB.
 * Mengagregasi log mentah 15-detik yang berumur lebih dari 3 hari ke ringkasan per jam (AcHourlySummary)
 * dan menghapus log mentah kadaluarsa untuk efisiensi ruang database MongoDB.
 */
Schedule::call(function () {
    app(DataRetentionService::class)->aggregateAndPrune(3);
})->dailyAt('02:00')->name('sikomat:daily-log-pruning');
