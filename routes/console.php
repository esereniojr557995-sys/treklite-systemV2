<?php

use Illuminate\Foundation\Inspiring;
use App\Services\BackupService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Automatic nightly backup (kept 30 days). Needs the scheduler running:
//   php artisan schedule:work            (while testing)
//   php artisan schedule:run  every minute via Windows Task Scheduler / cron   (on the shop computer)
Schedule::call(function () {
    $backups = app(BackupService::class);
    $backups->create('-auto');
    $backups->pruneAutomatic(30);
})->dailyAt('23:00')->name('nightly-backup')->withoutOverlapping();
