<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WorkItemDeletedNotification extends Notification
{
    use Queueable;
    protected $workItemName;

    public function __construct($workItemName)
    {
        // รับแค่ชื่อมา เพราะข้อมูล WorkItem กำลังจะถูกลบไปแล้ว
        $this->workItemName = $workItemName;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'type' => 'work_deleted',
            'title' => 'รายการถูกลบ',
            'message' => 'งานถูกลบออกจากระบบ: ' . $this->workItemName,
            'url' => route('work-items.index') // กลับไปหน้าหน้ารายการงานรวม
        ];
    }
}
