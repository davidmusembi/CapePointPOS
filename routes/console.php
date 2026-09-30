<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Low-stock notifications (also refreshed lazily when the bell is opened).
Artisan::command('pos:sync-notifications', function () {
    app(\App\Services\NotificationService::class)->syncLowStock(true);
    $this->info('Stock notifications synced.');
})->purpose('Create notifications for products at or below their alert level');

\Illuminate\Support\Facades\Schedule::command('pos:sync-notifications')->hourly();