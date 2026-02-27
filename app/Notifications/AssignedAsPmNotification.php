<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AssignedAsPmNotification extends Notification
{
    use Queueable;
    protected $workItem;

    public function __construct($workItem)
    {
        $this->workItem = $workItem;
    }

    public function via($notifiable)
    {
        return ['database']; // เก็บลง Database อย่างเดียว
    }

    public function toArray($notifiable)
    {
        return [
            'type' => 'assigned_pm',
            'title' => 'มอบหมายงานใหม่',
            'message' => 'คุณได้รับมอบหมายให้เป็นผู้ดูแล: ' . $this->workItem->name,
            'url' => route('work-items.show', $this->workItem->id)
        ];
    }
}
