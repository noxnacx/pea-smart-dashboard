<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// ✅ สั่งให้รันคำสั่งเคลียร์ถังขยะทุกๆ เที่ยงคืน
Schedule::command('trash:empty')->dailyAt('00:00');
