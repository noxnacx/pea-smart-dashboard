<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory; // ✅ เพิ่ม
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // ✅ เพิ่ม

class Issue extends Model
{
    use HasFactory, SoftDeletes; // ✅ เพิ่ม

    protected $fillable = [
        'work_item_id', 'user_id', 'title', 'type',
        'severity', 'status', 'description', 'solution', 'start_date', 'end_date',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function workItem()
    {
        return $this->belongsTo(WorkItem::class);
    }
}
