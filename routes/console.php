<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$tz = config('app.timezone', 'Asia/Baghdad');

Schedule::command('notifications:daily-digest')
    ->dailyAt('07:00')
    ->timezone($tz)
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/scheduler-digest.log'));

Schedule::command('notifications:due-soon')
    ->dailyAt('08:00')
    ->timezone($tz)
    ->withoutOverlapping();

Schedule::command('notifications:project-deadlines')
    ->dailyAt('09:00')
    ->timezone($tz)
    ->withoutOverlapping();

Schedule::command('notifications:overdue-tasks')
    ->everySixHours()
    ->timezone($tz)
    ->withoutOverlapping();

Schedule::command('tasks:purge-trash')
    ->daily()
    ->timezone($tz)
    ->withoutOverlapping();
