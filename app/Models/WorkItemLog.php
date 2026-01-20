<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkItemLog extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'log_date' => 'date',
    ];

    public function workItem()
    {
        return $this->belongsTo(WorkItem::class);
    }

    // *** เพิ่มส่วนนี้ครับ: เพื่อบอกว่า Log นี้ใครเป็นคนสร้าง ***
    public function uploader()
    {
        // เชื่อมโยงกับตาราง users ผ่านคอลัมน์ created_by
        return $this->belongsTo(User::class, 'created_by');
    }
}
