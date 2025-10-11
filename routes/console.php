<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule the auto-close command to run every hour
Schedule::command('rfq:auto-close-expired')->hourly();

// Schedule cleanup of expired invitations to run daily
Schedule::command('invitations:cleanup-expired')->daily();
