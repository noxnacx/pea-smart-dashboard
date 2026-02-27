<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// ✅ 1. สั่งให้รันคำสั่งเคลียร์ถังขยะทุกๆ เที่ยงคืน
Schedule::command('trash:empty')->dailyAt('00:00');

// 🚀 2. สำรองข้อมูล (Backup) ทั้ง Database และไฟล์แนบ ทุกๆ ตี 2
Schedule::command('backup:run')->dailyAt('02:00');

// 🧹 3. ลบไฟล์ Backup เก่าๆ ที่หมดอายุทิ้ง (เพื่อไม่ให้ฮาร์ดดิสก์เซิร์ฟเวอร์เต็ม) ทำงานตอน ตี 3
Schedule::command('backup:clean')->dailyAt('03:00');
