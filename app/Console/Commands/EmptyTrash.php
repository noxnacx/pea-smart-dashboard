<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\WorkItem;
use App\Models\Issue;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class EmptyTrash extends Command
{
    // ชื่อคำสั่งสำหรับเรียกใช้ผ่าน Terminal
    protected $signature = 'trash:empty';

    // คำอธิบายคำสั่ง
    protected $description = 'ลบข้อมูลในถังขยะที่ถูกทิ้งไว้เกิน 30 วัน แบบถาวร';

    public function handle()
    {
        $targetDate = Carbon::now()->subDays(30);
        $count = 0;

        // 1. ดึงข้อมูลที่ถูกลบ (Soft Delete) เกิน 30 วัน
        $oldTrashedItems = WorkItem::onlyTrashed()
            ->where('deleted_at', '<=', $targetDate)
            ->get();

        foreach ($oldTrashedItems as $item) {
            $this->cascadeForceDelete($item);
            $count++;
        }

        // 2. ลบ Issue ที่อยู่ในถังขยะเกิน 30 วัน
        $deletedIssues = Issue::onlyTrashed()
            ->where('deleted_at', '<=', $targetDate)
            ->forceDelete();

        $message = "Auto-Empty Trash ทำงานสำเร็จ: ลบงานถาวร {$count} รายการ, ลบปัญหา/ความเสี่ยง {$deletedIssues} รายการ";
        Log::info($message);
        $this->info($message);
    }

    // ฟังก์ชันลบลูกหลานแบบลูกโซ่
    private function cascadeForceDelete($item)
    {
        $trashedChildren = WorkItem::onlyTrashed()->where('parent_id', $item->id)->get();
        foreach ($trashedChildren as $child) {
            $this->cascadeForceDelete($child);
        }
        $item->forceDelete(); // ลบถาวร
    }
}
