<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Garmin only has new data after the watch syncs to the phone, so a few times
// a day is plenty and keeps API call volume low.
Schedule::command('garmin:sync')->everyFourHours()->withoutOverlapping();
