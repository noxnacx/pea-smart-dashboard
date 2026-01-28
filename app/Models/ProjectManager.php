<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectManager extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    // ✅ เพิ่มความสัมพันธ์: PM ดูแลหลาย WorkItem
    public function workItems()
    {
        return $this->hasMany(WorkItem::class);
    }
}
