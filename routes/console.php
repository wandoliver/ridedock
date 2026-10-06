<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Illuminate\Support\Facades\Schedule::command('app:prune-data --execute')
    ->dailyAt('03:00')->withoutOverlapping()->when(fn () => config('data-lifecycle.scheduled'));

Illuminate\Support\Facades\Schedule::command('app:monitor-operations --record')
    ->everyFiveMinutes()->onOneServer()->withoutOverlapping()->when(fn () => config('operations.scheduled_monitor'));
