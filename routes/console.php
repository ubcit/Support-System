<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('notifications:daily-digest')->dailyAt('07:00');
Schedule::command('notifications:due-soon')->dailyAt('08:00');
Schedule::command('notifications:project-deadlines')->dailyAt('09:00');
Schedule::command('notifications:overdue-tasks')->everySixHours();
Schedule::command('tasks:purge-trash')->daily();
