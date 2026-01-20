<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'work_item_id',
        'user_id',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
        'category'
    ];

    // เชื่อมกลับไปหาโครงการ
    public function workItem()
    {
        return $this->belongsTo(WorkItem::class);
    }

    // เชื่อมหาคนอัปโหลด (ใช้ชื่อ uploader เหมือนเดิม เพื่อให้หน้าบ้านไม่ต้องแก้เยอะ)
    public function uploader()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
