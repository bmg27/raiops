<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Scheduled Tasks
 * 
 * These sync commands keep RAINBO's cache tables synchronized with live RDS data.
 * Run 'php artisan schedule:work' in development or set up cron in production.
 * 
 * IMPORTANT: Tenant sync runs first, then user routing sync (tenant sync is prerequisite)
 */
Schedule::command('rainbo:sync-tenant-summaries')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('Scheduled sync-tenant-summaries failed');
    })
    ->description('Sync tenant summaries from all RDS instances');

Schedule::command('rainbo:sync-user-routing')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('Scheduled sync-user-routing failed');
    })
    ->description('Sync user email routing cache from master RDS');

// Optional: Sync ghost users daily (in case new RAINBO admins are added)
Schedule::command('sync:ghost-users')
    ->daily()
    ->at('02:00')
    ->withoutOverlapping()
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('Scheduled sync-ghost-users failed');
    })
    ->description('Sync ghost users to all RDS instances');
