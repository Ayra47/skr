<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('chat:prune')->daily();
Schedule::command('chat:delete-expired-files')->hourly();
Schedule::command('status:canary')->weekly();
