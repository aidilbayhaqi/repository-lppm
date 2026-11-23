<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('repository:recover-publishing')->everyFiveMinutes()->onOneServer()->withoutOverlapping();
// Alert using a production log collector; do not run on developer machines.
Schedule::command('repository:monitor-security')->everyFiveMinutes()->onOneServer()->withoutOverlapping()->when(fn () => app()->environment('production'));
