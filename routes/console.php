<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// In-app notifications for overdue invoices / supplier bills (also refreshed lazily when the bell is opened).
Artisan::command('pos:sync-notifications', function () {
    app(\App\Services\NotificationService::class)->syncOverdue(true);
    $this->info('Overdue notifications synced.');
})->purpose('Create notifications for overdue invoices and supplier bills');

\Illuminate\Support\Facades\Schedule::command('pos:sync-notifications')->hourly();