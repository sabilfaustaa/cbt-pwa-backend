<?php

use App\Console\Commands\AutoSubmitSesi;
use Illuminate\Support\Facades\Schedule;

// Auto-submit sesi kadaluarsa setiap menit (tanpa overlapping)
Schedule::command(AutoSubmitSesi::class)
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
