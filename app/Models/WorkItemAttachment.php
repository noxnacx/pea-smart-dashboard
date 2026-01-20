<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkItemAttachment extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function workItem()
    {
        return $this->belongsTo(WorkItem::class);
    }

    // ต้องมีฟังก์ชันนี้ด้วยนะครับ
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
