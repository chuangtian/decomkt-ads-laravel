<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('data:sync-design')->everyThirtyMinutes();
Schedule::command('data:sync-external-pages')->everyThirtyMinutes();
Schedule::command('data:sync-seo')->everyThirtyMinutes();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
