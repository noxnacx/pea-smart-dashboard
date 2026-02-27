<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class IssueCreatedNotification extends Notification
{
    use Queueable;
    protected $issue;
    protected $workItem;

    public function __construct($issue, $workItem)
    {
        $this->issue = $issue;
        $this->workItem = $workItem;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        $severityLabel = strtoupper($this->issue->severity);
        return [
            'type' => 'issue_created',
            'title' => 'รายงานปัญหาใหม่ (' . $severityLabel . ')',
            'message' => 'มีการรายงานปัญหาในงาน: ' . $this->workItem->name,
            'url' => route('work-items.show', $this->workItem->id)
        ];
    }
}
