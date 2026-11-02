<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('sheets:sync')
    ->dailyAt(config('sheets.sync_time'))
    ->withoutOverlapping()
    ->onOneServer();
