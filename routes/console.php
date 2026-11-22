<?php

use Illuminate\Support\Facades\Schedule;

// Production needs the scheduler running every minute: `php artisan schedule:run` (cron / Task Scheduler).

// Pre-create number counters for this year and next (avoids first-use races on 1 January).
Schedule::command('app:sync-sequences')->dailyAt('00:05');
