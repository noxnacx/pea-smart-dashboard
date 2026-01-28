<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code', 'division_id'];

    // ความสัมพันธ์: แผนก อยู่ใน กอง
    public function division()
    {
        return $this->belongsTo(Division::class);
    }

    // ความสัมพันธ์: แผนก มี User หลายคน
    public function users()
    {
        return $this->hasMany(User::class);
    }

    // ความสัมพันธ์: แผนก ดูแล Project หลายอัน
    public function workItems()
    {
        return $this->hasMany(WorkItem::class);
    }
}
