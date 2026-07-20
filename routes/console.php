<?php

use App\Console\Commands\CompleteShiftsCommand;
use App\Console\Commands\NotifyNoStartShiftsCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(NotifyNoStartShiftsCommand::class)->everyFiveMinutes();
Schedule::command(CompleteShiftsCommand::class)->everyFiveMinutes();
